<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;

class AdminAuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $auth = $request->getHeaderLine('Authorization');
        $expected = 'Bearer ' . ($_ENV['ADMIN_API_TOKEN'] ?? '');
        if (empty($_ENV['ADMIN_API_TOKEN']) || $auth === $expected) {
            return $handler->handle($request);
        }
        $res = new SlimResponse(401);
        $res->getBody()->write('Unauthorized');
        return $res;
    }
}