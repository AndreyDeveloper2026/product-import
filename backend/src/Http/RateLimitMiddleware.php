<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterFactory $factory,
        private readonly ResponseFactoryInterface $responses,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // за nginx REMOTE_ADDR одинаков для всех, поэтому лимитируем по пользователю из JWT
        $key = (string) ($request->getAttribute('user') ?? ($request->getServerParams()['REMOTE_ADDR'] ?? 'anonymous'));
        $limit = $this->factory->create($key)->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            $response = $this->responses->createResponse(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string) $retryAfter);
            $response->getBody()->write((string) json_encode(
                ['error' => 'Слишком много запросов на импорт, повторите позже', 'retryAfter' => $retryAfter],
                JSON_UNESCAPED_UNICODE,
            ));

            return $response;
        }

        return $handler->handle($request)
            ->withHeader('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
    }
}
