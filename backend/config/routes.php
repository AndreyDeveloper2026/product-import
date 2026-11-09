<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\DocsController;
use App\Controller\HealthController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use App\Http\JwtAuthMiddleware;
use App\Http\RateLimitMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    $app->get('/api/health', HealthController::class . ':check');
    $app->post('/api/auth/login', AuthController::class . ':login');
    $app->get('/api/openapi.json', DocsController::class . ':spec');
    $app->get('/api/docs', DocsController::class . ':ui');

    $app->group('/api', function (RouteCollectorProxy $api): void {
        $api->get('/products', ProductController::class . ':list');
        $api->get('/products/{id:[0-9]+}', ProductController::class . ':get');

        $api->post('/imports', ImportController::class . ':create')->add(RateLimitMiddleware::class);
        $api->get('/imports/latest', ImportController::class . ':latest');
        $api->get('/imports/{id:[0-9]+}', ImportController::class . ':get');
    })->add(JwtAuthMiddleware::class);
};
