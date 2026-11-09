<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ImportFormatException;
use App\Service\Import\ProductXlsxParser;
use App\Tests\Support\XlsxFactory;
use PHPUnit\Framework\TestCase;

final class ProductXlsxParserTest extends TestCase
{
    public function testParsesValidRowWithDiscountAttributesAndImages(): void
    {
        $file = XlsxFactory::create([
            ['A-1', 'Чайник', 'Описание', 1000, 750, "https://x.test/1.jpg\nhttps://x.test/2.jpg", 'Красный', '1 кг'],
        ]);

        $rows = iterator_to_array((new ProductXlsxParser())->parse($file));
        unlink($file);

        self::assertCount(1, $rows);
        $row = $rows[2]->row;
        self::assertNotNull($row);
        self::assertSame('A-1', $row->externalCode);
        self::assertSame('1000.00', $row->price);
        self::assertSame('25.00', $row->discount); // (1000-750)/1000*100
        self::assertSame(['Цвет' => 'Красный', 'Вес' => '1 кг'], $row->attributes);
        self::assertSame(['https://x.test/1.jpg', 'https://x.test/2.jpg'], $row->imageUrls);
    }

    public function testInvalidRowsAreReportedAndDoNotStopParsing(): void
    {
        $file = XlsxFactory::create([
            ['', 'Без кода', '', 100, 50, '', '', ''],
            ['B-2', 'Нормальный', '', '1 234,50', '', 'not a url', '', ''],
            ['C-3', 'Плохая цена', '', 'abc', '', '', '', ''],
        ]);

        $rows = iterator_to_array((new ProductXlsxParser())->parse($file), false);
        unlink($file);

        self::assertCount(3, $rows);
        self::assertNull($rows[0]->row);
        self::assertSame(['Не указан код товара'], $rows[0]->errors);

        self::assertNotNull($rows[1]->row);
        self::assertSame('1234.50', $rows[1]->row->price);
        self::assertSame('0.00', $rows[1]->row->discount);
        self::assertSame([], $rows[1]->row->imageUrls);
        self::assertCount(1, $rows[1]->row->warnings);

        self::assertNull($rows[2]->row);
    }

    public function testMissingRequiredColumnsFailWholeFile(): void
    {
        $file = XlsxFactory::create([['x']], ['Что-то', 'Другое']);

        $this->expectException(ImportFormatException::class);
        try {
            iterator_to_array((new ProductXlsxParser())->parse($file));
        } finally {
            unlink($file);
        }
    }

    public function testCountRowsSkipsHeaderAndEmptyRows(): void
    {
        $file = XlsxFactory::create([['A', 'n', '', 10, 5, '', '', ''], ['', '', '', '', '', '', '', ''], ['B', 'n', '', 10, 5, '', '', '']]);
        $count = (new ProductXlsxParser())->countRows($file);
        unlink($file);

        self::assertSame(2, $count);
    }
}
