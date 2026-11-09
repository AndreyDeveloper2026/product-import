<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Exception\ImportFormatException;
use DateTimeInterface;
use Generator;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Потоковый парсер xlsx (OpenSpout): не грузит весь файл в память.
 *
 * Ожидаемые колонки (регистр не важен, есть синонимы):
 *  - Код / Внешний код / external_code          (обязательно)
 *  - Наименование / Название / name             (обязательно)
 *  - Цена продажи / Цена / price                (обязательно)
 *  - Закупочная цена / purchase_price           (для расчёта скидки)
 *  - Описание / description
 *  - Изображения / Фото / images                (ссылки через перевод строки, запятую или ;)
 *  - «Доп. поле: Имя» / «Доп. поле Имя»          (любое количество, key = «Имя»)
 */
final class ProductXlsxParser
{
    private const MAX_IMAGES_PER_ROW = 20;

    private const ALIASES = [
        'code' => ['код', 'внешний код', 'external_code', 'external code', 'код товара'],
        'name' => ['наименование', 'название', 'name'],
        'description' => ['описание', 'description'],
        'price' => [
            'цена продажи',
            'цена',
            'price',
            'цена: цена продажи',
        ],
        'purchase' => ['закупочная цена', 'цена закупки', 'purchase_price', 'purchase price'],
        'images' => ['изображения', 'изображение', 'фото', 'картинки', 'images', 'image'],
    ];

    /**
     * @return Generator<int, ParsedRow>
     */
    public function parse(string $path): Generator
    {
        $reader = $this->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $map = null;
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    ++$rowNumber;
                    $cells = $row->toArray();
                    if ($map === null) {
                        $map = $this->buildMap($cells);
                        continue;
                    }
                    if ($this->isEmpty($cells)) {
                        continue;
                    }
                    yield $rowNumber => $this->parseRow($rowNumber, $cells, $map);
                }
                break; // только первый лист
            }
        } finally {
            $reader->close();
        }
    }

    /** Количество строк данных (без заголовка и пустых строк). */
    public function countRows(string $path): int
    {
        $reader = $this->open($path);
        $count = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $first = true;
                foreach ($sheet->getRowIterator() as $row) {
                    if ($first) {
                        $first = false;
                        continue;
                    }
                    if (!$this->isEmpty($row->toArray())) {
                        ++$count;
                    }
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $count;
    }

    private function open(string $path): Reader
    {
        $options = new Options();
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true; // чтобы номера строк совпадали с Excel

        $reader = new Reader($options);
        try {
            $reader->open($path);
        } catch (IOException $e) {
            throw new ImportFormatException('Не удалось открыть xlsx: ' . $e->getMessage(), 0, $e);
        }

        return $reader;
    }

    /**
     * @param array<int, mixed> $headers
     *
     * @return array{cols: array<string, int>, attributes: array<int, string>}
     */
    private function buildMap(array $headers): array
    {
        $cols = [];
        $attributes = [];

        foreach ($headers as $index => $header) {
            $title = trim($this->cellToString($header));
            if ($title === '') {
                continue;
            }

            if (preg_match('/^доп\.?\s*поле\s*[:\-–—]?\s*(.*)$/iu', $title, $m) === 1) {
                $key = trim($m[1]);
                $attributes[$index] = $key !== '' ? $key : $title;
                continue;
            }

            $normalized = mb_strtolower((string) preg_replace('/\s+/u', ' ', $title));
            foreach (self::ALIASES as $field => $aliases) {
                if (!isset($cols[$field]) && in_array($normalized, $aliases, true)) {
                    $cols[$field] = (int) $index;
                    break;
                }
            }
        }

        $missing = array_diff(['code', 'name', 'price'], array_keys($cols));
        if ($missing !== []) {
            $titles = ['code' => 'Код', 'name' => 'Наименование', 'price' => 'Цена продажи'];
            throw new ImportFormatException(
                'В файле нет обязательных колонок: ' . implode(', ', array_map(static fn (string $f): string => $titles[$f], $missing)),
            );
        }

        return ['cols' => $cols, 'attributes' => $attributes];
    }

    /**
     * @param array<int, mixed>                                                $cells
     * @param array{cols: array<string, int>, attributes: array<int, string>} $map
     */
    private function parseRow(int $rowNumber, array $cells, array $map): ParsedRow
    {
        $cols = $map['cols'];
        $errors = [];
        $warnings = [];

        $code = trim($this->cellToString($cells[$cols['code']] ?? null));
        $name = trim($this->cellToString($cells[$cols['name']] ?? null));
        $price = $this->decimal($cells[$cols['price']] ?? null);

        if ($code === '') {
            $errors[] = 'Не указан код товара';
        } elseif (mb_strlen($code) > 100) {
            $errors[] = 'Код товара длиннее 100 символов';
        }
        if ($name === '') {
            $errors[] = 'Не указано наименование';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Наименование длиннее 255 символов';
        }
        if ($price === null || $price <= 0 || $price >= 1.0E+10) {
            $errors[] = 'Цена продажи должна быть положительным числом';
        }

        $purchase = null;
        if (isset($cols['purchase'])) {
            $rawPurchase = $cells[$cols['purchase']] ?? null;
            $purchase = $this->decimal($rawPurchase);
            if ($purchase === null && trim($this->cellToString($rawPurchase)) !== '') {
                $errors[] = 'Закупочная цена не является числом';
            } elseif ($purchase !== null && $purchase < 0) {
                $errors[] = 'Закупочная цена не может быть отрицательной';
            }
        }

        if ($errors !== [] || $price === null) {
            return new ParsedRow($rowNumber, null, $errors);
        }

        $discount = $purchase === null ? 0.0 : round(($price - $purchase) / $price * 100, 2);

        $attributes = [];
        foreach ($map['attributes'] as $index => $key) {
            $value = trim($this->cellToString($cells[$index] ?? null));
            if ($value !== '') {
                $attributes[$key] = $value;
            }
        }

        $imageUrls = [];
        if (isset($cols['images'])) {
            foreach ($this->splitUrls($this->cellToString($cells[$cols['images']] ?? null)) as $url) {
                if (!$this->isHttpUrl($url)) {
                    $warnings[] = sprintf('Некорректная ссылка на изображение пропущена: %s', mb_substr($url, 0, 120));
                    continue;
                }
                if (mb_strlen($url) > 2048) {
                    $warnings[] = 'Слишком длинная ссылка на изображение пропущена';
                    continue;
                }
                $imageUrls[$url] = $url;
            }
            if (count($imageUrls) > self::MAX_IMAGES_PER_ROW) {
                $warnings[] = sprintf('Изображений больше %d — лишние пропущены', self::MAX_IMAGES_PER_ROW);
                $imageUrls = array_slice($imageUrls, 0, self::MAX_IMAGES_PER_ROW, true);
            }
        }

        $description = isset($cols['description'])
            ? trim($this->cellToString($cells[$cols['description']] ?? null))
            : '';

        return new ParsedRow($rowNumber, new ProductRow(
            $code,
            $name,
            $description !== '' ? $description : null,
            number_format($price, 2, '.', ''),
            number_format($discount, 2, '.', ''),
            $attributes,
            array_values($imageUrls),
            $warnings,
        ));
    }

    /** @param array<int, mixed> $cells */
    private function isEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($this->cellToString($cell)) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return floor($value) === $value && abs($value) < 1.0E+15
                ? sprintf('%.0f', $value)
                : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        try {
            return (string) $value; // @phpstan-ignore-line
        } catch (Throwable) {
            return '';
        }
    }

    private function decimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace(["\xC2\xA0", ' '], '', trim($value));
        $normalized = str_replace(',', '.', $normalized);

        return $normalized !== '' && is_numeric($normalized) ? (float) $normalized : null;
    }

    /** @return list<string> */
    private function splitUrls(string $cell): array
    {
        $parts = preg_split('/[\s,;]+/u', trim($cell), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : array_values($parts);
    }

    private function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
