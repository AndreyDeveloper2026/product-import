<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;

final readonly class ProductPage
{
    /**
     * @param list<Product> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $limit,
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->limit)));
    }
}
