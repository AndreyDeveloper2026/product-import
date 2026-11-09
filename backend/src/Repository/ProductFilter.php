<?php

declare(strict_types=1);

namespace App\Repository;

final readonly class ProductFilter
{
    public function __construct(
        public int $page = 1,
        public int $limit = 20,
        public ?string $name = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
    ) {
    }
}
