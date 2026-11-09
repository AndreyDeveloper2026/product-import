<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ApiException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Throwable;

final class JsonErrorHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, Throwable $e, bool $displayErrorDetails): ResponseInterface
    {
        if ($e instanceof ApiException) {
            $status = $e->getStatus();
            $message = $e->getMessage();
        } elseif ($e instanceof HttpException) {
            $status = $e->getCode() >= 400 ? $e->getCode() : 500;
            $message = $e->getMessage();
        } else {
            $status = 500;
            $message = $this->debug ? $e->getMessage() : 'Internal server error';
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        $response = $this->responses->createResponse($status)->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode(['error' => $message], JSON_UNESCAPED_UNICODE));

        return $response;
    }
}
