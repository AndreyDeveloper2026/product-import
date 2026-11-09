<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Bootstrap;
use App\Service\Image\ImageDownloaderInterface;
use DI\Container;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

abstract class ContainerTestCase extends TestCase
{
    protected Container $container;
    protected EntityManagerInterface $em;
    protected FakeImageDownloader $images;
    protected string $tmp;

    /** @param array<string, string> $extraEnv */
    protected function boot(array $extraEnv = []): void
    {
        $this->tmp = sys_get_temp_dir() . '/pi_' . bin2hex(random_bytes(6));
        mkdir($this->tmp, 0775, true);
        $this->images = new FakeImageDownloader();

        $this->container = Bootstrap::container($extraEnv + [
            'APP_ENV' => 'test',
            'DB_DRIVER' => 'pdo_sqlite',
            'IMPORT_DIR' => $this->tmp . '/imports',
            'IMAGE_DIR' => $this->tmp . '/images',
            'VAR_DIR' => $this->tmp . '/var',
            'MESSENGER_TRANSPORT_DSN' => 'in-memory://',
            'IMPORT_RATE_LIMIT' => '1000',
            'JWT_SECRET' => str_repeat('s', 40),
            'ADMIN_USER' => 'admin',
            'ADMIN_PASSWORD' => 'secret',
        ], [ImageDownloaderInterface::class => $this->images]);

        $this->em = $this->container->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        if (isset($this->tmp) && is_dir($this->tmp)) {
            $this->rrmdir($this->tmp);
        }
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
