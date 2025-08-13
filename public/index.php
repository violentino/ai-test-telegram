<?php
use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Error middleware
$displayErrorDetails = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);

// Basic health route
$app->get('/health', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Routes
(require __DIR__ . '/../src/routes.php')($app);

$app->run();