<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CatalogController
{
    public function webapp(Request $request, Response $response): Response
    {
        $html = file_get_contents(__DIR__ . '/../../webapp/index.html');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    public function list(Request $request, Response $response): Response
    {
        $filters = [
            'category' => $request->getQueryParams()['category'] ?? null,
            'gender' => $request->getQueryParams()['gender'] ?? null,
            'size' => $request->getQueryParams()['size'] ?? null,
            'color' => $request->getQueryParams()['color'] ?? null,
        ];
        $repo = new \App\Repositories\ProductRepository();
        $products = $repo->list(array_filter($filters));
        $response->getBody()->write(json_encode(['items' => $products]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $repo = new \App\Repositories\ProductRepository();
        $product = $repo->getById($id);
        if ($product) {
            $response->getBody()->write(json_encode($product));
            return $response->withHeader('Content-Type', 'application/json');
        }
        return $response->withStatus(404);
    }

    private function stubProducts(): array
    {
        return [];
    }
}