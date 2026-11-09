<?php

declare(strict_types=1);

namespace App\Service\Image;

use App\Exception\ImageDownloadException;

interface ImageDownloaderInterface
{
    /**
     * Скачивает изображение и возвращает путь относительно каталога изображений.
     *
     * @throws ImageDownloadException
     */
    public function download(string $url, string $externalCode): string;

    /** Удаляет локальный файл (молча, если его нет). */
    public function delete(string $path): void;
}
