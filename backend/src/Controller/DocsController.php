<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Finder\Finder;

final class DocsController extends AbstractController
{
    public function spec(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $finder = (new Finder())->files()->in([__DIR__, __DIR__ . '/../OpenApi'])->name('*.php');
        $json = (new Generator())->generate($finder)?->toJson() ?? '{}';

        $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function ui(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = <<<'HTML'
            <!doctype html>
            <html lang="en"><head><meta charset="utf-8"><title>API docs</title>
            <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"></head>
            <body><div id="swagger"></div>
            <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>window.ui = SwaggerUIBundle({url: '/api/openapi.json', dom_id: '#swagger'});</script>
            </body></html>
            HTML;
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
