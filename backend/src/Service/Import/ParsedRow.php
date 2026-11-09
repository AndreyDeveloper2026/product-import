<?php

declare(strict_types=1);

namespace App\Service\Import;

final readonly class ParsedRow
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public int $rowNumber,
        public ?ProductRow $row,
        public array $errors = [],
    ) {
    }
}
