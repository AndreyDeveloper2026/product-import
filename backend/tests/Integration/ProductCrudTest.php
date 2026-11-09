<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductImage;
use App\Fixtures\ProductFixtures;
use App\Repository\ProductFilter;
use App\Repository\ProductRepository;
use App\Tests\Support\ContainerTestCase;

final class ProductCrudTest extends ContainerTestCase
{
    private ProductRepository $repo;

    protected function setUp(): void
    {
        $this->boot();
        $this->repo = $this->container->get(ProductRepository::class);
    }

    public function testCreateReadUpdateDelete(): void
    {
        $product = new Product('CRUD-1');
        $product->applyData('Товар', 'Описание', '199.90', '12.50');
        $product->replaceAttributes(['Цвет' => 'Синий']);
        $product->syncImages(['https://x.test/a.jpg' => 'products/a.jpg']);
        $this->repo->save($product);
        $id = $product->getId();
        $this->em->clear();

        $loaded = $this->repo->find((int) $id);
        self::assertNotNull($loaded);
        self::assertSame('199.90', $loaded->getPrice());
        self::assertSame('Синий', $loaded->getAttributes()->first()->getValue());
        self::assertSame('products/a.jpg', $loaded->getImages()->first()->getPath());

        $loaded->applyData('Новое имя', null, '250.00', '0.00');
        $this->repo->save($loaded);
        $this->em->clear();
        self::assertSame('Новое имя', $this->repo->findByExternalCode('CRUD-1')?->getName());

        $this->repo->remove($this->repo->findByExternalCode('CRUD-1'));
        $this->em->clear();
        self::assertNull($this->repo->findByExternalCode('CRUD-1'));
        self::assertCount(0, $this->em->getRepository(ProductAttribute::class)->findAll());
        self::assertCount(0, $this->em->getRepository(ProductImage::class)->findAll());
    }

    public function testFixturesAndPagination(): void
    {
        (new ProductFixtures(25))->load($this->em);
        $this->em->clear();

        $page = $this->repo->paginate(new ProductFilter(page: 3, limit: 10));
        self::assertSame(25, $page->total);
        self::assertCount(5, $page->items);
        self::assertSame(3, $page->totalPages());

        $byName = $this->repo->paginate(new ProductFilter(name: 'товар 2'));
        self::assertSame(7, $byName->total); // 2, 20..25

        $byPrice = $this->repo->paginate(new ProductFilter(priceMin: 200, priceMax: 300));
        self::assertGreaterThan(0, $byPrice->total);
        foreach ($byPrice->items as $p) {
            self::assertGreaterThanOrEqual(200.0, (float) $p->getPrice());
            self::assertLessThanOrEqual(300.0, (float) $p->getPrice());
        }
    }
}
