<?php
use Slim\App;
use App\Controllers\TelegramController;
use App\Controllers\CatalogController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\AdminController;
use App\Controllers\AdminOrdersController;
use App\Controllers\TonController;

return function (App $app) {
    $app->post('/webhook/{secret}', [TelegramController::class, 'handle']);

    // WebApp endpoints
    $app->get('/webapp', [CatalogController::class, 'webapp']);
    $app->get('/api/catalog', [CatalogController::class, 'list']);
    $app->get('/api/product/{id}', [CatalogController::class, 'get']);
    $app->post('/api/cart', [CartController::class, 'add']);
    $app->get('/api/cart', [CartController::class, 'view']);
    $app->delete('/api/cart/{productId}', [CartController::class, 'remove']);
    $app->post('/api/checkout', [CheckoutController::class, 'start']);
    $app->get('/api/ton/rate', [TonController::class, 'rate']);

    // Payment webhooks
    $app->post('/webhooks/stripe', [CheckoutController::class, 'stripeWebhook']);
    $app->post('/webhooks/ton/{secret}', [CheckoutController::class, 'tonCallback']);

    // Admin endpoints (protected by simple bearer token)
    $adminAuth = new \App\Middleware\AdminAuthMiddleware();
    $app->get('/admin', [AdminController::class, 'dashboard'])->add($adminAuth);
    $app->post('/admin/product', [AdminController::class, 'createProduct'])->add($adminAuth);
    $app->put('/admin/product/{id}', [AdminController::class, 'updateProduct'])->add($adminAuth);
    $app->delete('/admin/product/{id}', [AdminController::class, 'deleteProduct'])->add($adminAuth);

    // Admin orders
    $app->get('/admin/orders', [AdminOrdersController::class, 'list'])->add($adminAuth);
    $app->get('/admin/orders/{id}', [AdminOrdersController::class, 'get'])->add($adminAuth);
    $app->put('/admin/orders/{id}/status', [AdminOrdersController::class, 'updateStatus'])->add($adminAuth);
};