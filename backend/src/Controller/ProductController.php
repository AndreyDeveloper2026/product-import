<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiException;
use App\Http\Presenter;
use App\Repository\ProductFilter;
use App\Repository\ProductRepository;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController extends AbstractController
{
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly Presenter $presenter,
    ) {
    }

    #[OA\Get(
        path: '/api/products',
        summary: 'Список товаров (серверная пагинация, поиск, фильтр по цене)',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'name', in: 'query', description: 'Подстрока в наименовании', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'priceMin', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'priceMax', in: 'query', schema: new OA\Schema(type: 'number')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ProductPage')),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ],
    )]
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $q = $request->getQueryParams();

        $filter = new ProductFilter(
            page: max(1, (int) ($q['page'] ?? 1)),
            limit: min(self::MAX_LIMIT, max(1, (int) ($q['limit'] ?? 20))),
            name: isset($q['name']) && is_string($q['name']) ? trim($q['name']) : null,
            priceMin: $this->number($q['priceMin'] ?? null, 'priceMin'),
            priceMax: $this->number($q['priceMax'] ?? null, 'priceMax'),
        );

        $page = $this->products->paginate($filter);
        $ids = array_map(static fn ($p): int => (int) $p->getId(), $page->items);
        $thumbs = $this->products->firstImagePaths($ids);

        return $this->json($response, [
            'items' => array_map(
                fn ($p): array => $this->presenter->productListItem($p, $thumbs[(int) $p->getId()] ?? null),
                $page->items,
            ),
            'page' => $page->page,
            'limit' => $page->limit,
            'total' => $page->total,
            'totalPages' => $page->totalPages(),
        ]);
    }

    #[OA\Get(
        path: '/api/products/{id}',
        summary: 'Карточка товара',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ProductDetail')),
            new OA\Response(response: 404, description: 'Не найден'),
        ],
    )]
    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $product = $this->products->find((int) $args['id']);
        if ($product === null) {
            throw ApiException::notFound('Товар не найден');
        }

        return $this->json($response, $this->presenter->productDetail($product));
    }

    private function number(mixed $value, string $field): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw ApiException::validation(sprintf('Параметр %s должен быть числом', $field));
        }

        return (float) $value;
    }
}
