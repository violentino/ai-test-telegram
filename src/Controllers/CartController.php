<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CartController
{
    private static array $userCarts = [];

    public function add(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $userId = $this->resolveUserId($request);
        if (!$userId) { return $response->withStatus(401); }
        $productId = (int)($data['productId'] ?? 0);
        $quantity = max(1, (int)($data['quantity'] ?? 1));
        $variant = $data['variant'] ?? [];

        $repo = new \App\Repositories\CartRepository();
        $repo->addItem($userId, $productId, $quantity, $variant['size'] ?? null, $variant['color'] ?? null);
        $items = $repo->getItems($userId);
        $response->getBody()->write(json_encode(['ok' => true, 'cart' => $items]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function view(Request $request, Response $response): Response
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) { return $response->withStatus(401); }
        $repo = new \App\Repositories\CartRepository();
        $items = $repo->getItems($userId);
        $response->getBody()->write(json_encode(['items' => $items]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function remove(Request $request, Response $response, array $args): Response
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) { return $response->withStatus(401); }
        $productId = (int)($args['productId'] ?? 0);
        $repo = new \App\Repositories\CartRepository();
        $repo->removeProduct($userId, $productId);
        $items = $repo->getItems($userId);
        $response->getBody()->write(json_encode(['items' => $items]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function resolveUserId(Request $request): ?int
    {
        $tgInitData = $request->getHeaderLine('X-Telegram-Init-Data');
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        return \App\Services\TelegramInit::getUserIdFromInitData($tgInitData, $botToken);
    }
}