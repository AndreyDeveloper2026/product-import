<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiException;
use App\Http\Presenter;
use App\Repository\ImportJobRepository;
use App\Service\Import\ImportSubmissionService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ImportController extends AbstractController
{
    public function __construct(
        private readonly ImportSubmissionService $submission,
        private readonly ImportJobRepository $jobs,
        private readonly Presenter $presenter,
    ) {
    }

    #[OA\Post(
        path: '/api/imports',
        summary: 'Загрузить xlsx и поставить импорт в очередь (асинхронно)',
        description: 'Rate limit: ограничено числом запросов в минуту на пользователя (429 + Retry-After).',
        security: [['bearerAuth' => []]],
        tags: ['Import'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')],
                ),
            ),
        ),
        responses: [
            new OA\Response(response: 202, description: 'Задача создана', content: new OA\JsonContent(ref: '#/components/schemas/ImportJob')),
            new OA\Response(response: 413, description: 'Файл слишком большой'),
            new OA\Response(response: 422, description: 'Невалидный файл'),
            new OA\Response(response: 429, description: 'Слишком много запросов'),
        ],
    )]
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = $request->getUploadedFiles()['file'] ?? null;
        if (is_array($file)) {
            $file = null; // file[] не поддерживаем
        }

        $job = $this->submission->submit($file);

        return $this->json($response, $this->presenter->importJob($job), 202);
    }

    #[OA\Get(
        path: '/api/imports/{id}',
        summary: 'Статус и отчёт об ошибках импорта (для polling)',
        security: [['bearerAuth' => []]],
        tags: ['Import'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ImportJob')),
            new OA\Response(response: 404, description: 'Не найден'),
        ],
    )]
    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $job = $this->jobs->find((int) $args['id']);
        if ($job === null) {
            throw ApiException::notFound('Импорт не найден');
        }

        return $this->json($response, $this->presenter->importJob($job));
    }

    #[OA\Get(
        path: '/api/imports/latest',
        summary: 'Последний импорт',
        security: [['bearerAuth' => []]],
        tags: ['Import'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ImportJob')),
            new OA\Response(response: 404, description: 'Импортов ещё не было'),
        ],
    )]
    public function latest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $job = $this->jobs->latest();
        if ($job === null) {
            throw ApiException::notFound('Импортов ещё не было');
        }

        return $this->json($response, $this->presenter->importJob($job));
    }
}
