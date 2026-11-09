<?php

declare(strict_types=1);

namespace App;

use App\Http\JsonErrorHandler;
use DI\Container;
use DI\ContainerBuilder;
use Slim\App;
use Slim\Factory\AppFactory;

final class Bootstrap
{
    /**
     * @param array<string, mixed> $env       переменные окружения (по умолчанию getenv())
     * @param array<string, mixed> $overrides определения контейнера (для тестов)
     */
    public static function container(?array $env = null, array $overrides = []): Container
    {
        date_default_timezone_set('UTC');

        require_once dirname(__DIR__) . '/config/settings.php';

        $env ??= getenv();
        $settings = \app_settings($env);

        $definitions = (require dirname(__DIR__) . '/config/container.php')($settings);

        $builder = new ContainerBuilder();
        $builder->addDefinitions($definitions);
        $builder->addDefinitions($overrides);

        return $builder->build();
    }

    /**
     * @param array<string, mixed> $env
     * @param array<string, mixed> $overrides
     */
    public static function app(?array $env = null, array $overrides = []): App
    {
        $container = self::container($env, $overrides);

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();

        (require dirname(__DIR__) . '/config/routes.php')($app);

        $errors = $app->addErrorMiddleware(false, false, false);
        $errors->setDefaultErrorHandler($container->get(JsonErrorHandler::class));

        return $app;
    }
}
