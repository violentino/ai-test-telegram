<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AdminController
{
    public function dashboard(Request $request, Response $response): Response
    {
        $response->getBody()->write('<h1>Admin</h1><p>Use API to manage products and orders.</p>');
        return $response;
    }

    public function createProduct(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $images = $data['images'] ?? [];
        $variants = $data['variants'] ?? [];
        $id = (new \App\Repositories\ProductRepository())->create($data, $images, $variants);
        $product = (new \App\Repositories\ProductRepository())->getById($id);
        $response->getBody()->write(json_encode(['ok' => true, 'product' => $product]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateProduct(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody() ?? [];
        $images = $data['images'] ?? null;
        $variants = $data['variants'] ?? null;
        (new \App\Repositories\ProductRepository())->update($id, $data, $images, $variants);
        $product = (new \App\Repositories\ProductRepository())->getById($id);
        $response->getBody()->write(json_encode(['ok' => true, 'product' => $product]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function deleteProduct(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        (new \App\Repositories\ProductRepository())->delete($id);
        $response->getBody()->write(json_encode(['ok' => true, 'deleted' => $id]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}