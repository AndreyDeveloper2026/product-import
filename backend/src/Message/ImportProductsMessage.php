<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ImportProductsMessage
{
    public function __construct(public int $importJobId)
    {
    }
}
