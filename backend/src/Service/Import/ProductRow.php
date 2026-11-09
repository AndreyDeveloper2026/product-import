<?php

declare(strict_types=1);

namespace App\Service\Import;

final readonly class ProductRow
{
    /**
     * @param array<string, string> $attributes
     * @param list<string>          $imageUrls
     * @param list<string>          $warnings
     */
    public function __construct(
        public string $externalCode,
        public string $name,
        public ?string $description,
        public string $price,
        public string $discount,
        public array $attributes,
        public array $imageUrls,
        public array $warnings = [],
    ) {
    }
}
