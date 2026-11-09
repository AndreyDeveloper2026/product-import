<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** Ошибка, которую можно безопасно показать клиенту (с HTTP-статусом). */
class ApiException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 400)
    {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public static function validation(string $message): self
    {
        return new self($message, 422);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self($message, 401);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self($message, 404);
    }
}
