<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use GuzzleHttp\Client;

class TonController
{
    public function rate(Request $request, Response $response): Response
    {
        $currency = strtoupper($request->getQueryParams()['currency'] ?? ($_ENV['CURRENCY'] ?? 'USD'));
        $client = new Client();
        try {
            $base = rtrim($_ENV['TON_API_BASE'] ?? 'https://tonapi.io', '/');
            $res = $client->get($base . '/v2/rates?tokens=ton&currencies=' . urlencode(strtolower($currency)), [ 'timeout' => 5 ]);
            $data = json_decode((string)$res->getBody(), true);
            $rate = $data['rates']['TON'][$currency] ?? null;
            $response->getBody()->write(json_encode(['currency' => $currency, 'rate' => $rate]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            return $response->withStatus(502);
        }
    }
}