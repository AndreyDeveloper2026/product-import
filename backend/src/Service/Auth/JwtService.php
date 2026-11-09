<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Exception\ApiException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds,
    ) {
    }

    public function issue(string $subject): string
    {
        $now = time();

        return JWT::encode(['sub' => $subject, 'iat' => $now, 'exp' => $now + $this->ttlSeconds], $this->secret, 'HS256');
    }

    /** @return string subject */
    public function verify(string $token): string
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (Throwable) {
            throw ApiException::unauthorized('Invalid or expired token');
        }

        return (string) ($payload->sub ?? '');
    }

    public function ttl(): int
    {
        return $this->ttlSeconds;
    }
}
