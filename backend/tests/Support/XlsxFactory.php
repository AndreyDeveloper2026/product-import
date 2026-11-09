<?php

declare(strict_types=1);

namespace App\Tests\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class XlsxFactory
{
    public const HEADER = ['Код', 'Наименование', 'Описание', 'Цена продажи', 'Закупочная цена', 'Изображения', 'Доп. поле: Цвет', 'Доп. поле: Вес'];

    /**
     * @param list<list<mixed>> $rows строки данных (без заголовка)
     * @param list<string>|null $header
     */
    public static function create(array $rows, ?array $header = null): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('xlsx_', true) . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($header ?? self::HEADER));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }
}
