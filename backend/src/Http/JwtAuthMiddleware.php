<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ApiException;
use App\Service\Auth\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m) !== 1) {
            throw ApiException::unauthorized('Missing bearer token');
        }

        return $handler->handle($request->withAttribute('user', $this->jwt->verify($m[1])));
    }
}
