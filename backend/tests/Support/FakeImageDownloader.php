<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Exception\ImageDownloadException;
use App\Service\Image\ImageDownloaderInterface;

final class FakeImageDownloader implements ImageDownloaderInterface
{
    /** @var list<string> */
    public array $downloaded = [];
    /** @var list<string> */
    public array $deleted = [];

    public function download(string $url, string $externalCode): string
    {
        if (str_contains($url, 'broken')) {
            throw new ImageDownloadException('HTTP 404 при загрузке ' . $url);
        }
        $this->downloaded[] = $url;

        return 'products/test/' . md5($externalCode . $url) . '.jpg';
    }

    public function delete(string $path): void
    {
        $this->deleted[] = $path;
    }
}
