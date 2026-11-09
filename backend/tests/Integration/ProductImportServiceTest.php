<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\ImportJob;
use App\Entity\Product;
use App\Repository\ImportJobRepository;
use App\Service\Import\ProductImportService;
use App\Tests\Support\ContainerTestCase;
use App\Tests\Support\XlsxFactory;

final class ProductImportServiceTest extends ContainerTestCase
{
    protected function setUp(): void
    {
        $this->boot();
    }

    public function testImportCreatesProductsAndCollectsReport(): void
    {
        $job = $this->runImport([
            ['P-1', 'Первый', 'desc', 1000, 800, 'https://x.test/a.jpg https://x.test/broken.jpg', 'Красный', '1 кг'],
            ['', 'Без кода', '', 10, 5, '', '', ''],
            ['P-2', 'Второй', '', 500, '', '', '', ''],
        ]);

        self::assertSame(ImportJob::STATUS_COMPLETED, $job->getStatus());
        self::assertSame(3, $job->getTotalRows());
        self::assertSame(3, $job->getProcessedRows());
        self::assertSame(2, $job->getSucceededRows());
        self::assertSame(1, $job->getFailedRows());

        $levels = array_column($job->getReport(), 'level');
        self::assertContains('error', $levels);
        self::assertContains('warning', $levels); // broken.jpg

        $p1 = $this->em->getRepository(Product::class)->findOneBy(['externalCode' => 'P-1']);
        self::assertNotNull($p1);
        self::assertSame('20.00', $p1->getDiscount());
        self::assertCount(2, $p1->getAttributes());
        self::assertCount(1, $p1->getImages());
    }

    public function testReimportIsUpsertWithoutDuplicates(): void
    {
        $this->runImport([['U-1', 'Старое имя', '', 100, 50, 'https://x.test/1.jpg', 'Красный', '']]);
        $this->runImport([['U-1', 'Новое имя', '', 200, 100, 'https://x.test/2.jpg', 'Синий', '']]);

        $products = $this->em->getRepository(Product::class)->findAll();
        self::assertCount(1, $products);
        self::assertSame('Новое имя', $products[0]->getName());
        self::assertSame('200.00', $products[0]->getPrice());
        self::assertSame('50.00', $products[0]->getDiscount());
        self::assertCount(1, $products[0]->getAttributes());
        self::assertSame('Синий', $products[0]->getAttributes()->first()->getValue());
        self::assertCount(1, $products[0]->getImages());
        self::assertSame('https://x.test/2.jpg', $products[0]->getImages()->first()->getUrl());
        self::assertCount(1, $this->images->deleted, 'старый файл изображения удалён');
    }

    public function testBadFileMarksJobFailed(): void
    {
        $file = XlsxFactory::create([['x']], ['Что-то']);
        $job = $this->submitAndRun($file);

        self::assertSame(ImportJob::STATUS_FAILED, $job->getStatus());
        self::assertStringContainsString('обязательных колонок', (string) $job->getErrorMessage());
    }

    /** @param list<list<mixed>> $rows */
    private function runImport(array $rows): ImportJob
    {
        return $this->submitAndRun(XlsxFactory::create($rows));
    }

    private function submitAndRun(string $file): ImportJob
    {
        $job = new ImportJob('test.xlsx', $file);
        $this->container->get(ImportJobRepository::class)->add($job);
        $id = (int) $job->getId();

        $this->container->get(ProductImportService::class)->run($id);

        $this->em->clear();
        $loaded = $this->em->find(ImportJob::class, $id);
        self::assertNotNull($loaded);
        self::assertFileDoesNotExist($file, 'временный файл импорта удаляется');

        return $loaded;
    }
}
