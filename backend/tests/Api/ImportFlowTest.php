<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Bootstrap;
use App\Fixtures\ProductFixtures;
use App\Message\ImportProductsHandler;
use App\Message\ImportProductsMessage;
use App\Service\Image\ImageDownloaderInterface;
use App\Tests\Support\ContainerTestCase;
use App\Tests\Support\FakeImageDownloader;
use App\Tests\Support\XlsxFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/** e2e: полный HTTP-стек Slim (роутинг, JWT, rate limit, валидация, очередь) в процессе. */
final class ImportFlowTest extends ContainerTestCase
{
    private App $app;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/pi_' . bin2hex(random_bytes(6));
        mkdir($this->tmp, 0775, true);
        $this->bootApp();
    }

    public function testHealthIsPublic(): void
    {
        $res = $this->call('GET', '/api/health');
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('ok', $this->body($res)['status']);
    }

    public function testProtectedRoutesRequireToken(): void
    {
        self::assertSame(401, $this->call('GET', '/api/products')->getStatusCode());
        self::assertSame(401, $this->call('POST', '/api/auth/login', null, ['username' => 'admin', 'password' => 'wrong'])->getStatusCode());
    }

    public function testListPaginationAndFilters(): void
    {
        (new ProductFixtures(25))->load($this->em);
        $token = $this->login();

        $page = $this->body($this->call('GET', '/api/products', $token, null, ['page' => '2', 'limit' => '10']));
        self::assertCount(10, $page['items']);
        self::assertSame(25, $page['total']);
        self::assertSame(3, $page['totalPages']);
        self::assertSame(2, $page['page']);

        $filtered = $this->body($this->call('GET', '/api/products', $token, null, ['priceMin' => '200', 'priceMax' => '300', 'name' => 'товар']));
        self::assertGreaterThan(0, $filtered['total']);

        self::assertSame(422, $this->call('GET', '/api/products', $token, null, ['priceMin' => 'abc'])->getStatusCode());
        self::assertSame(404, $this->call('GET', '/api/products/99999', $token)->getStatusCode());
    }

    public function testAsyncImportEndToEnd(): void
    {
        $token = $this->login();
        $file = XlsxFactory::create([
            ['E-1', 'Товар E1', 'Описание', 1000, 700, 'https://x.test/1.jpg', 'Красный', '2 кг'],
            ['', 'Битая строка', '', 1, 1, '', '', ''],
        ]);

        $res = $this->upload($token, $file, 'products.xlsx');
        self::assertSame(202, $res->getStatusCode());
        $job = $this->body($res);
        self::assertSame('pending', $job['status']);

        // сообщение действительно ушло в очередь, а не обработано синхронно
        /** @var InMemoryTransport $transport */
        $transport = $this->container->get('messenger.transport.async');
        self::assertCount(1, $transport->getSent());
        self::assertSame(0, $this->body($this->call('GET', '/api/products', $token))['total']);

        // «воркер»
        $this->container->get(ImportProductsHandler::class)(new ImportProductsMessage($job['id']));
        $this->em->clear();

        $done = $this->body($this->call('GET', '/api/imports/' . $job['id'], $token));
        self::assertSame('completed', $done['status']);
        self::assertSame(100, $done['progress']);
        self::assertSame(1, $done['succeededRows']);
        self::assertSame(1, $done['failedRows']);
        self::assertSame('error', $done['report'][0]['level']);

        $latest = $this->body($this->call('GET', '/api/imports/latest', $token));
        self::assertSame($job['id'], $latest['id']);

        $list = $this->body($this->call('GET', '/api/products', $token));
        $detail = $this->body($this->call('GET', '/api/products/' . $list['items'][0]['id'], $token));
        self::assertSame('Товар E1', $detail['name']);
        self::assertSame(30.0, $detail['discount']);
        self::assertSame('Цвет', $detail['attributes'][0]['key']);
        self::assertSame('https://x.test/1.jpg', $detail['images'][0]['url']);
        self::assertStringStartsWith('/uploads/products/', $detail['images'][0]['publicUrl']);
    }

    public function testUploadValidation(): void
    {
        $token = $this->login();

        $txt = $this->tmp . '/fake.xlsx';
        file_put_contents($txt, 'not a zip');
        self::assertSame(422, $this->upload($token, $txt, 'fake.xlsx')->getStatusCode(), 'ложный xlsx отклоняется по структуре/MIME');

        $other = $this->tmp . '/data.csv';
        file_put_contents($other, 'a,b');
        self::assertSame(422, $this->upload($token, $other, 'data.csv')->getStatusCode(), 'расширение');

        $req = $this->request('POST', '/api/imports', $token);
        self::assertSame(422, $this->app->handle($req)->getStatusCode(), 'файл не передан');
    }

    public function testImportEndpointIsRateLimited(): void
    {
        $this->bootApp(['IMPORT_RATE_LIMIT' => '1', 'IMPORT_RATE_INTERVAL' => '1 hour']);
        $token = $this->login();

        $f1 = XlsxFactory::create([['R-1', 'n', '', 10, 5, '', '', '']]);
        $f2 = XlsxFactory::create([['R-2', 'n', '', 10, 5, '', '', '']]);

        self::assertSame(202, $this->upload($token, $f1, 'a.xlsx')->getStatusCode());
        $limited = $this->upload($token, $f2, 'b.xlsx');
        self::assertSame(429, $limited->getStatusCode());
        self::assertNotSame('', $limited->getHeaderLine('Retry-After'));
    }

    // --- helpers ---------------------------------------------------------------------------------

    /** @param array<string, string> $extraEnv */
    private function bootApp(array $extraEnv = []): void
    {
        $this->images = new FakeImageDownloader();
        $this->app = Bootstrap::app($extraEnv + [
            'APP_ENV' => 'test',
            'DB_DRIVER' => 'pdo_sqlite',
            'IMPORT_DIR' => $this->tmp . '/imports',
            'IMAGE_DIR' => $this->tmp . '/images',
            'VAR_DIR' => $this->tmp . '/var' . bin2hex(random_bytes(3)),
            'MESSENGER_TRANSPORT_DSN' => 'in-memory://',
            'IMPORT_RATE_LIMIT' => '1000',
            'JWT_SECRET' => str_repeat('s', 40),
            'ADMIN_USER' => 'admin',
            'ADMIN_PASSWORD' => 'secret',
        ], [ImageDownloaderInterface::class => $this->images]);

        /** @var \DI\Container $container */
        $container = $this->app->getContainer();
        $this->container = $container;
        $this->em = $container->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function login(): string
    {
        $res = $this->call('POST', '/api/auth/login', null, ['username' => 'admin', 'password' => 'secret']);
        self::assertSame(200, $res->getStatusCode());

        return (string) $this->body($res)['token'];
    }

    private function upload(string $token, string $path, string $clientName): ResponseInterface
    {
        $file = new UploadedFile($path, $clientName, 'application/octet-stream', (int) filesize($path), UPLOAD_ERR_OK, false);

        return $this->app->handle($this->request('POST', '/api/imports', $token)->withUploadedFiles(['file' => $file]));
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string>     $query
     */
    private function call(string $method, string $uri, ?string $token = null, ?array $json = null, array $query = []): ResponseInterface
    {
        $req = $this->request($method, $uri, $token)->withQueryParams($query);
        if ($json !== null) {
            $req = $req->withParsedBody($json);
        }

        return $this->app->handle($req);
    }

    private function request(string $method, string $uri, ?string $token): \Psr\Http\Message\ServerRequestInterface
    {
        $req = (new ServerRequestFactory())->createServerRequest($method, 'http://localhost' . $uri);

        return $token !== null ? $req->withHeader('Authorization', 'Bearer ' . $token) : $req;
    }

    /** @return array<string, mixed> */
    private function body(ResponseInterface $res): array
    {
        $res->getBody()->rewind();

        return json_decode((string) $res->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
