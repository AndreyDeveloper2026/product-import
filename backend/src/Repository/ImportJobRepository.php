<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Чтение/создание — через ORM. Обновление прогресса — через DBAL напрямую:
 * воркер вызывает em->clear() после каждой строки, а при ошибке flush EntityManager закрывается,
 * при этом статус задачи должен обновляться в любом случае.
 */
class ImportJobRepository
{
    private const MAX_REPORT_ENTRIES = 1000;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?ImportJob
    {
        return $this->em->find(ImportJob::class, $id);
    }

    public function latest(): ?ImportJob
    {
        return $this->em->getRepository(ImportJob::class)->findOneBy([], ['id' => 'DESC']);
    }

    public function add(ImportJob $job): void
    {
        $this->em->persist($job);
        $this->em->flush();
    }

    public function markStarted(int $id, int $totalRows): void
    {
        $this->conn()->update('import_jobs', [
            'status' => ImportJob::STATUS_PROCESSING,
            'total_rows' => $totalRows,
            'started_at' => $this->now(),
        ], ['id' => $id]);
    }

    /**
     * @param list<array{row: int, level: string, message: string}> $report
     */
    public function saveProgress(int $id, int $processed, int $succeeded, int $failed, array $report): void
    {
        $this->conn()->update('import_jobs', [
            'processed_rows' => $processed,
            'succeeded_rows' => $succeeded,
            'failed_rows' => $failed,
            'report' => $this->encode($report),
        ], ['id' => $id]);
    }

    /**
     * @param list<array{row: int, level: string, message: string}> $report
     */
    public function markFinished(
        int $id,
        string $status,
        int $processed,
        int $succeeded,
        int $failed,
        array $report,
        ?string $errorMessage = null,
    ): void {
        $this->conn()->update('import_jobs', [
            'status' => $status,
            'processed_rows' => $processed,
            'succeeded_rows' => $succeeded,
            'failed_rows' => $failed,
            'report' => $this->encode($report),
            'error_message' => $errorMessage,
            'file_path' => null,
            'finished_at' => $this->now(),
        ], ['id' => $id]);
    }

    private function conn(): Connection
    {
        return $this->em->getConnection();
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    /**
     * @param list<array{row: int, level: string, message: string}> $report
     */
    private function encode(array $report): string
    {
        return json_encode(array_slice($report, 0, self::MAX_REPORT_ENTRIES), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
