<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $env
 *
 * @return array<string, mixed>
 */
function app_settings(array $env): array
{
    $get = static fn (string $key, string $default = ''): string => isset($env[$key]) && $env[$key] !== false ? (string) $env[$key] : $default;
    $root = dirname(__DIR__);

    $driver = $get('DB_DRIVER', 'pdo_mysql');
    $db = $driver === 'pdo_sqlite'
        ? ['driver' => 'pdo_sqlite', 'memory' => true]
        : [
            'driver' => $driver,
            'host' => $get('DB_HOST', 'db'),
            'port' => (int) $get('DB_PORT', '3306'),
            'dbname' => $get('DB_NAME', 'app'),
            'user' => $get('DB_USER', 'app'),
            'password' => $get('DB_PASSWORD', 'app'),
            'charset' => 'utf8mb4',
        ];

    return [
        'env' => $get('APP_ENV', 'prod'),
        'debug' => $get('APP_DEBUG', '0') === '1',
        'db' => $db,
        'jwt_secret' => $get('JWT_SECRET', 'change-me-change-me-change-me-32chars'),
        'jwt_ttl' => (int) $get('JWT_TTL', '3600'),
        'admin_user' => $get('ADMIN_USER', 'admin'),
        'admin_password' => $get('ADMIN_PASSWORD', 'admin123'),
        'messenger_dsn' => $get('MESSENGER_TRANSPORT_DSN', 'in-memory://'),
        'import_dir' => $get('IMPORT_DIR', $root . '/storage/imports'),
        'image_dir' => $get('IMAGE_DIR', $root . '/storage/images'),
        'image_public_url' => $get('IMAGE_PUBLIC_URL', '/uploads'),
        'image_max_bytes' => (int) $get('IMAGE_MAX_BYTES', '5242880'),
        'import_max_bytes' => (int) $get('IMPORT_MAX_BYTES', '10485760'),
        'var_dir' => $get('VAR_DIR', $root . '/var'),
        'import_rate_limit' => (int) $get('IMPORT_RATE_LIMIT', '5'),
        'import_rate_interval' => $get('IMPORT_RATE_INTERVAL', '1 minute'),
    ];
}
