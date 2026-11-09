<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

class ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?Product
    {
        return $this->em->find(Product::class, $id);
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->em->getRepository(Product::class)->findOneBy(['externalCode' => $externalCode]);
    }

    public function save(Product $product): void
    {
        $this->em->persist($product);
        $this->em->flush();
    }

    public function remove(Product $product): void
    {
        $this->em->remove($product);
        $this->em->flush();
    }

    public function paginate(ProductFilter $filter): ProductPage
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->orderBy('p.id', 'ASC')
            ->setFirstResult(($filter->page - 1) * $filter->limit)
            ->setMaxResults($filter->limit);

        if ($filter->name !== null && $filter->name !== '') {
            $qb->andWhere('p.name LIKE :name')
                ->setParameter('name', '%' . addcslashes($filter->name, '%_\\') . '%');
        }
        if ($filter->priceMin !== null) {
            $qb->andWhere('p.price >= :min')->setParameter('min', $filter->priceMin);
        }
        if ($filter->priceMax !== null) {
            $qb->andWhere('p.price <= :max')->setParameter('max', $filter->priceMax);
        }

        $paginator = new Paginator($qb, false);

        /** @var list<Product> $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return new ProductPage($items, count($paginator), $filter->page, $filter->limit);
    }

    /**
     * Первые изображения для набора товаров одним запросом (без N+1 в списке).
     *
     * @param list<int> $productIds
     *
     * @return array<int, string> productId => path
     */
    public function firstImagePaths(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(i.product) AS pid', 'i.path AS path')
            ->from(ProductImage::class, 'i')
            ->where('IDENTITY(i.product) IN (:ids)')
            ->orderBy('i.id', 'ASC')
            ->setParameter('ids', $productIds, ArrayParameterType::INTEGER)
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['pid']] ??= (string) $row['path'];
        }

        return $result;
    }
}
