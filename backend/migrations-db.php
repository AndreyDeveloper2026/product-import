<?php

declare(strict_types=1);

return [
    'driver' => 'pdo_mysql',
    'host' => getenv('DB_HOST') ?: 'db',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'dbname' => getenv('DB_NAME') ?: 'app',
    'user' => getenv('DB_USER') ?: 'app',
    'password' => getenv('DB_PASSWORD') ?: 'app',
    'charset' => 'utf8mb4',
];
