<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class HealthController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[OA\Get(
        path: '/api/health',
        summary: 'Health check',
        tags: ['System'],
        responses: [
            new OA\Response(response: 200, description: 'Сервис и БД доступны'),
            new OA\Response(response: 503, description: 'БД недоступна'),
        ],
    )]
    public function check(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
        } catch (Throwable) {
            return $this->json($response, ['status' => 'degraded', 'db' => 'down'], 503);
        }

        return $this->json($response, ['status' => 'ok', 'db' => 'up']);
    }
}
