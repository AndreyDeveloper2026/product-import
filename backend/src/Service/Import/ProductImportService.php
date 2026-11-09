<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use App\Entity\Product;
use App\Exception\ImageDownloadException;
use App\Exception\ImportFormatException;
use App\Repository\ImportJobRepository;
use App\Repository\ProductRepository;
use App\Service\Image\ImageDownloaderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Оркестрация импорта: парсинг → (скачивание изображений) → транзакционный upsert → отчёт.
 * Некорректные строки не прерывают импорт, а попадают в отчёт.
 */
class ProductImportService
{
    private const PROGRESS_EVERY = 20;
    private const MAX_REPORT_ENTRIES = 1000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
        private readonly ImportJobRepository $jobs,
        private readonly ProductXlsxParser $parser,
        private readonly ImageDownloaderInterface $images,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(int $jobId): void
    {
        $job = $this->jobs->find($jobId);
        if ($job === null || $job->getStatus() !== ImportJob::STATUS_PENDING) {
            return; // идемпотентность: повторная доставка сообщения ничего не ломает
        }

        $path = (string) $job->getFilePath();
        $processed = $succeeded = $failed = 0;
        /** @var list<array{row: int, level: string, message: string}> $report */
        $report = [];

        try {
            $this->jobs->markStarted($jobId, $this->parser->countRows($path));

            foreach ($this->parser->parse($path) as $parsed) {
                ++$processed;

                if ($parsed->row === null) {
                    ++$failed;
                    $this->addReport($report, $parsed->rowNumber, 'error', implode('; ', $parsed->errors));
                } else {
                    try {
                        foreach ($this->importRow($parsed->row) as $warning) {
                            $this->addReport($report, $parsed->rowNumber, 'warning', $warning);
                        }
                        ++$succeeded;
                    } catch (Throwable $e) {
                        if (!$this->em->isOpen()) {
                            throw new RuntimeException('Ошибка базы данных, импорт прерван: ' . $e->getMessage(), 0, $e);
                        }
                        ++$failed;
                        $this->logger->warning('Import row failed', ['job' => $jobId, 'row' => $parsed->rowNumber, 'error' => $e->getMessage()]);
                        $this->addReport($report, $parsed->rowNumber, 'error', 'Не удалось сохранить товар: ' . $e->getMessage());
                    }
                }

                $this->em->clear();

                if ($processed % self::PROGRESS_EVERY === 0) {
                    $this->jobs->saveProgress($jobId, $processed, $succeeded, $failed, $report);
                }
            }

            $this->jobs->markFinished($jobId, ImportJob::STATUS_COMPLETED, $processed, $succeeded, $failed, $report);
        } catch (ImportFormatException $e) {
            $this->jobs->markFinished($jobId, ImportJob::STATUS_FAILED, $processed, $succeeded, $failed, $report, $e->getMessage());
        } catch (Throwable $e) {
            $this->logger->error('Import failed', ['job' => $jobId, 'error' => $e->getMessage()]);
            $this->jobs->markFinished($jobId, ImportJob::STATUS_FAILED, $processed, $succeeded, $failed, $report, 'Внутренняя ошибка импорта: ' . $e->getMessage());
        } finally {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Upsert одного товара. Сеть (скачивание картинок) — вне транзакции БД,
     * запись товара + атрибутов + изображений — в одной транзакции.
     *
     * @return list<string> предупреждения (например, не скачалась картинка)
     */
    private function importRow(ProductRow $row): array
    {
        $warnings = $row->warnings;

        $product = $this->products->findByExternalCode($row->externalCode) ?? new Product($row->externalCode);

        $known = [];
        foreach ($product->getImages() as $image) {
            $known[$image->getUrl()] = $image->getPath();
        }

        $urlToPath = [];
        $downloaded = [];
        foreach ($row->imageUrls as $url) {
            if (isset($known[$url])) {
                $urlToPath[$url] = $known[$url];
                continue;
            }
            try {
                $local = $this->images->download($url, $row->externalCode);
                $urlToPath[$url] = $local;
                $downloaded[] = $local;
            } catch (ImageDownloadException $e) {
                $warnings[] = $e->getMessage();
            }
        }

        $removed = [];
        try {
            $this->em->wrapInTransaction(function () use ($product, $row, $urlToPath, &$removed): void {
                $product->applyData($row->name, $row->description, $row->price, $row->discount);
                $product->replaceAttributes($row->attributes);
                $removed = $product->syncImages($urlToPath);
                $this->em->persist($product);
            });
        } catch (Throwable $e) {
            foreach ($downloaded as $local) {
                $this->images->delete($local);
            }
            throw $e;
        }

        foreach ($removed as $local) {
            $this->images->delete($local);
        }

        return $warnings;
    }

    /**
     * @param list<array{row: int, level: string, message: string}> $report
     */
    private function addReport(array &$report, int $row, string $level, string $message): void
    {
        if (count($report) < self::MAX_REPORT_ENTRIES) {
            $report[] = ['row' => $row, 'level' => $level, 'message' => $message];
        }
    }
}
