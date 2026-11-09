<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\ProductController;
use App\Http\JsonErrorHandler;
use App\Http\Presenter;
use App\Http\RateLimitMiddleware;
use App\Message\ImportProductsHandler;
use App\Message\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use App\Service\Auth\JwtService;
use App\Service\Image\HttpImageDownloader;
use App\Service\Image\ImageDownloaderInterface;
use App\Service\Import\ImportSubmissionService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @param array<string, mixed> $settings
 *
 * @return array<string, mixed>
 */
return static function (array $settings): array {
    return [
        'settings' => $settings,

        LoggerInterface::class => static function (): LoggerInterface {
            return new Logger('app', [new StreamHandler('php://stderr')]);
        },

        ResponseFactoryInterface::class => static fn (): ResponseFactoryInterface => new ResponseFactory(),

        EntityManagerInterface::class => static function (ContainerInterface $c): EntityManagerInterface {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');
            $cache = $s['env'] === 'prod'
                ? new PhpFilesAdapter('doctrine_metadata', 0, $s['var_dir'] . '/cache')
                : new ArrayAdapter();

            $config = ORMSetup::createAttributeMetadataConfiguration(
                [dirname(__DIR__) . '/src/Entity'],
                $s['env'] !== 'prod',
                $s['var_dir'] . '/proxies',
                $cache,
            );

            $config->enableNativeLazyObjects(true);

            return new EntityManager(DriverManager::getConnection($s['db'], $config), $config);
        },

        HttpClientInterface::class => static fn (): HttpClientInterface => HttpClient::create(),

        ImageDownloaderInterface::class => static function (ContainerInterface $c): ImageDownloaderInterface {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');

            return new HttpImageDownloader($c->get(HttpClientInterface::class), $s['image_dir'], $s['image_max_bytes']);
        },

        JwtService::class => static function (ContainerInterface $c): JwtService {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');

            return new JwtService($s['jwt_secret'], $s['jwt_ttl']);
        },

        AuthController::class => static function (ContainerInterface $c): AuthController {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');

            return new AuthController($c->get(JwtService::class), $s['admin_user'], $s['admin_password']);
        },

        Presenter::class => static function (ContainerInterface $c): Presenter {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');

            return new Presenter($s['image_public_url']);
        },

        ImportSubmissionService::class => static function (ContainerInterface $c): ImportSubmissionService {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');

            return new ImportSubmissionService(
                $c->get(ImportJobRepository::class),
                $c->get(MessageBusInterface::class),
                $s['import_dir'],
                $s['import_max_bytes'],
            );
        },

        JsonErrorHandler::class => static function (ContainerInterface $c): JsonErrorHandler {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');

            return new JsonErrorHandler($c->get(ResponseFactoryInterface::class), $c->get(LoggerInterface::class), (bool) $s['debug']);
        },

        // --- Messenger ---------------------------------------------------------------------------
        'messenger.transport.async' => static function (ContainerInterface $c): TransportInterface {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');
            $factory = new TransportFactory([new AmqpTransportFactory(), new InMemoryTransportFactory()]);

            return $factory->createTransport((string) $s['messenger_dsn'], ['auto_setup' => true], new PhpSerializer());
        },

        MessageBusInterface::class => static function (ContainerInterface $c): MessageBusInterface {
            return new MessageBus([
                new SendMessageMiddleware(new SendersLocator(
                    [ImportProductsMessage::class => ['messenger.transport.async']],
                    $c,
                )),
                new HandleMessageMiddleware(new HandlersLocator([
                    ImportProductsMessage::class => [$c->get(ImportProductsHandler::class)],
                ])),
            ]);
        },

        // --- Rate limiting --------------------------------------------------------------------------
        'rate_limiter.import' => static function (ContainerInterface $c): RateLimiterFactory {
            /** @var array<string, mixed> $s */
            $s = $c->get('settings');

            return new RateLimiterFactory(
                ['id' => 'import', 'policy' => 'sliding_window', 'limit' => $s['import_rate_limit'], 'interval' => $s['import_rate_interval']],
                new CacheStorage(new FilesystemAdapter('rate_limit', 0, $s['var_dir'] . '/cache')),
            );
        },

        RateLimitMiddleware::class => static fn (ContainerInterface $c): RateLimitMiddleware => new RateLimitMiddleware(
            $c->get('rate_limiter.import'),
            $c->get(ResponseFactoryInterface::class),
        ),
    ];
};
