<?php

declare(strict_types=1);

namespace App\Service\Image;

use App\Exception\ImageDownloadException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpImageDownloader implements ImageDownloaderInterface
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $storageDir,
        private readonly int $maxBytes = 5_242_880,
        private readonly int $timeoutSeconds = 15,
    ) {
    }

    public function download(string $url, string $externalCode): string
    {
        $this->assertPublicHost($url);

        try {
            $response = $this->http->request('GET', $url, [
                'timeout' => $this->timeoutSeconds,
                'max_duration' => $this->timeoutSeconds * 2,
                'max_redirects' => 3,
                'headers' => ['Accept' => 'image/*', 'User-Agent' => 'ProductImporter/1.0'],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new ImageDownloadException(sprintf('HTTP %d при загрузке %s', $response->getStatusCode(), $url));
            }

            $contentType = strtolower(trim(explode(';', $response->getHeaders()['content-type'][0] ?? '')[0]));
            $ext = self::EXTENSIONS[$contentType] ?? null;
            if ($ext === null) {
                throw new ImageDownloadException(sprintf('Неподдерживаемый тип содержимого "%s" (%s)', $contentType, $url));
            }

            $body = '';
            foreach ($this->http->stream($response) as $chunk) {
                $body .= $chunk->getContent();
                if (strlen($body) > $this->maxBytes) {
                    $response->cancel();
                    throw new ImageDownloadException(sprintf('Изображение больше %d байт (%s)', $this->maxBytes, $url));
                }
            }
        } catch (ExceptionInterface $e) {
            throw new ImageDownloadException(sprintf('Не удалось скачать %s: %s', $url, $e->getMessage()), 0, $e);
        }

        if ($body === '' || @getimagesizefromstring($body) === false) {
            throw new ImageDownloadException(sprintf('Файл не является изображением (%s)', $url));
        }

        $hash = sha1($externalCode . '|' . $url);
        $relative = sprintf('products/%s/%s.%s', substr($hash, 0, 2), $hash, $ext);
        $absolute = rtrim($this->storageDir, '/') . '/' . $relative;

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new ImageDownloadException('Не удалось создать каталог для изображений');
        }
        if (file_put_contents($absolute, $body) === false) {
            throw new ImageDownloadException('Не удалось сохранить изображение на диск');
        }

        return $relative;
    }

    public function delete(string $path): void
    {
        $absolute = rtrim($this->storageDir, '/') . '/' . ltrim($path, '/');
        if (str_contains($path, '..')) {
            return;
        }
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    /** Защита от SSRF: запрещаем локальные/приватные адреса. */
    private function assertPublicHost(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new ImageDownloadException('Некорректный URL: ' . $url);
        }
        $host = trim($host, '[]');

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            throw new ImageDownloadException('Не удалось определить адрес хоста: ' . $host);
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new ImageDownloadException('Загрузка с внутренних адресов запрещена: ' . $host);
            }
        }
    }
}
