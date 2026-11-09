<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Entity\Product;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Сидер для products, product_attributes, product_images (демо-данные). */
final class ProductFixtures implements FixtureInterface
{
    public function __construct(private readonly int $count = 45)
    {
    }

    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= $this->count; ++$i) {
            $price = 100 + $i * 25.5;
            $purchase = $price * 0.7;

            $product = new Product(sprintf('SEED-%04d', $i));
            $product->applyData(
                sprintf('Тестовый товар %d', $i),
                sprintf('Описание тестового товара №%d', $i),
                number_format($price, 2, '.', ''),
                number_format(($price - $purchase) / $price * 100, 2, '.', ''),
            );
            $product->replaceAttributes(['Цвет' => $i % 2 === 0 ? 'Красный' : 'Синий', 'Вес' => sprintf('%d г', 100 + $i)]);
            $product->syncImages([
                sprintf('https://example.com/img/%d.jpg', $i) => sprintf('products/seed/%d.jpg', $i),
            ]);

            $manager->persist($product);
        }

        $manager->flush();
    }
}
