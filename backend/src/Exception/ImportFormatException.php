<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** Файл не соответствует ожидаемой структуре (нет обязательных колонок и т.п.). Импорт целиком невозможен. */
final class ImportFormatException extends RuntimeException
{
}
