<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Database\Connection;

class AdminOrdersController
{
    public function list(Request $request, Response $response): Response
    {
        $pdo = Connection::getPdo();
        $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 200");
        $orders = $stmt->fetchAll();
        $response->getBody()->write(json_encode(['items' => $orders]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $pdo = Connection::getPdo();
        $order = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $order->execute([':id' => $id]);
        $o = $order->fetch();
        if (!$o) return $response->withStatus(404);
        $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $items->execute([':id' => $id]);
        $o['items'] = $items->fetchAll();
        $response->getBody()->write(json_encode($o));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = $request->getParsedBody() ?? [];
        $status = $data['status'] ?? null;
        if (!in_array($status, ['pending','paid','failed','canceled'], true)) {
            return $response->withStatus(400);
        }
        $pdo = Connection::getPdo();
        $pdo->prepare("UPDATE orders SET status=:s WHERE id=:id")->execute([':s' => $status, ':id' => $id]);
        return $this->get($request, $response, ['id' => $id]);
    }
}