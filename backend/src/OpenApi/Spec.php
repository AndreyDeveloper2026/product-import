<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(title: 'Product Import API', version: '1.0.0', description: 'Импорт товаров из xlsx через очередь, список и карточка товара')]
#[OA\Server(url: '/', description: 'Current host')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', scheme: 'bearer', bearerFormat: 'JWT')]
#[OA\Schema(
    schema: 'ProductListItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'externalCode', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'price', type: 'number', format: 'float'),
        new OA\Property(property: 'discount', type: 'number', format: 'float', description: '(price - purchase) / price * 100'),
        new OA\Property(property: 'thumbnailUrl', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'ProductPage',
    properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/ProductListItem')),
        new OA\Property(property: 'page', type: 'integer'),
        new OA\Property(property: 'limit', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'totalPages', type: 'integer'),
    ],
)]
#[OA\Schema(
    schema: 'ProductDetail',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'externalCode', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float'),
        new OA\Property(property: 'discount', type: 'number', format: 'float'),
        new OA\Property(property: 'attributes', type: 'array', items: new OA\Items(
            properties: [new OA\Property(property: 'key', type: 'string'), new OA\Property(property: 'value', type: 'string')],
            type: 'object',
        )),
        new OA\Property(property: 'images', type: 'array', items: new OA\Items(
            properties: [
                new OA\Property(property: 'url', type: 'string'),
                new OA\Property(property: 'path', type: 'string'),
                new OA\Property(property: 'publicUrl', type: 'string'),
            ],
            type: 'object',
        )),
    ],
)]
#[OA\Schema(
    schema: 'ImportJob',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'completed', 'failed']),
        new OA\Property(property: 'originalName', type: 'string'),
        new OA\Property(property: 'totalRows', type: 'integer'),
        new OA\Property(property: 'processedRows', type: 'integer'),
        new OA\Property(property: 'succeededRows', type: 'integer'),
        new OA\Property(property: 'failedRows', type: 'integer'),
        new OA\Property(property: 'progress', type: 'integer', description: 'Проценты 0..100'),
        new OA\Property(property: 'report', type: 'array', items: new OA\Items(
            properties: [
                new OA\Property(property: 'row', type: 'integer'),
                new OA\Property(property: 'level', type: 'string', enum: ['error', 'warning']),
                new OA\Property(property: 'message', type: 'string'),
            ],
            type: 'object',
        )),
        new OA\Property(property: 'errorMessage', type: 'string', nullable: true),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'finishedAt', type: 'string', format: 'date-time', nullable: true),
    ],
)]
final class Spec
{
}
