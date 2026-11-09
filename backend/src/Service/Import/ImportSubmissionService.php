<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use App\Exception\ApiException;
use App\Message\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use ZipArchive;

/** Валидирует загруженный файл, создаёт задачу и кладёт сообщение в очередь. */
class ImportSubmissionService
{
    private const ALLOWED_MIME = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];

    public function __construct(
        private readonly ImportJobRepository $jobs,
        private readonly MessageBusInterface $bus,
        private readonly string $importDir,
        private readonly int $maxBytes,
    ) {
    }

    public function submit(?UploadedFileInterface $file): ImportJob
    {
        if ($file === null) {
            throw ApiException::validation('Файл не передан (ожидается multipart-поле "file")');
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw ApiException::validation('Ошибка загрузки файла (код ' . $file->getError() . ')');
        }
        if (($file->getSize() ?? 0) > $this->maxBytes) {
            throw new ApiException(sprintf('Файл больше %d МБ', intdiv($this->maxBytes, 1_048_576)), 413);
        }

        $originalName = basename((string) $file->getClientFilename());
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw ApiException::validation('Допустим только файл с расширением .xlsx');
        }

        if (!is_dir($this->importDir) && !mkdir($this->importDir, 0775, true) && !is_dir($this->importDir)) {
            throw new ApiException('Не удалось подготовить каталог импорта', 500);
        }
        $target = rtrim($this->importDir, '/') . '/' . bin2hex(random_bytes(16)) . '.xlsx';
        $file->moveTo($target);

        if (!$this->looksLikeXlsx($target)) {
            @unlink($target);
            throw ApiException::validation('Файл не является корректным xlsx (проверка MIME/структуры не пройдена)');
        }

        $job = new ImportJob($originalName, $target);
        $this->jobs->add($job);

        try {
            $this->bus->dispatch(new ImportProductsMessage((int) $job->getId()));
        } catch (Throwable $e) {
            $this->jobs->markFinished((int) $job->getId(), ImportJob::STATUS_FAILED, 0, 0, 0, [], 'Очередь недоступна');
            @unlink($target);
            throw new ApiException('Очередь недоступна, повторите позже', 503);
        }

        return $job;
    }

    private function looksLikeXlsx(string $path): bool
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED_MIME, true)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('xl/workbook.xml') !== false && $zip->locateName('[Content_Types].xml') !== false;
        $zip->close();

        return $ok;
    }
}
