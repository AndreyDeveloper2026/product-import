<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiException;
use App\Service\Auth\JwtService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly string $adminUser,
        private readonly string $adminPassword,
    ) {
    }

    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Получить JWT',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'password', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Токен выдан'),
            new OA\Response(response: 401, description: 'Неверные учётные данные'),
        ],
    )]
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $user = (string) ($body['username'] ?? '');
        $password = (string) ($body['password'] ?? '');

        $userOk = hash_equals($this->adminUser, $user);
        $passOk = hash_equals($this->adminPassword, $password);
        if (!($userOk && $passOk)) {
            throw ApiException::unauthorized('Неверный логин или пароль');
        }

        return $this->json($response, [
            'token' => $this->jwt->issue($user),
            'expiresIn' => $this->jwt->ttl(),
        ]);
    }
}
