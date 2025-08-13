<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Stripe\StripeClient;
use GuzzleHttp\Client;

class CheckoutController
{
    private ?StripeClient $stripe = null;
    private Client $httpClient;

    public function __construct()
    {
        $sk = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        if (!empty($sk)) {
            $this->stripe = new StripeClient($sk);
        }
        $this->httpClient = new Client();
    }

    public function start(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $paymentMethod = $data['paymentMethod'] ?? 'card'; // card | ton
        $currency = strtoupper($_ENV['CURRENCY'] ?? 'USD');

        $tgInitData = $request->getHeaderLine('X-Telegram-Init-Data');
        $userId = \App\Services\TelegramInit::getUserIdFromInitData($tgInitData, $_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
        if (!$userId) { return $this->json($response, ['error' => 'Unauthorized'], 401); }

        $cartRepo = new \App\Repositories\CartRepository();
        $items = $cartRepo->getItems($userId);

        $shippingMethod = $data['shippingMethod'] ?? 'pickup';
        $shippingAddress = $data['shippingAddress'] ?? null;

        if ($paymentMethod === 'card') {
            if (!$this->stripe) {
                return $this->json($response, ['error' => 'Card processor not configured'], 400);
            }
            $amount = $this->calculateAmountCents($items, $currency);
            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'payment',
                'success_url' => ($_ENV['APP_URL'] ?? '') . '/webapp?success=1',
                'cancel_url' => ($_ENV['APP_URL'] ?? '') . '/webapp?canceled=1',
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => $amount,
                        'product_data' => [ 'name' => 'Order' ],
                    ],
                    'quantity' => 1,
                ]],
            ]);
            (new \App\Repositories\OrderRepository())->createFromCart($userId, 'card', $items, $currency, null, $session->id, null, $shippingMethod, $shippingAddress);
            return $this->json($response, ['checkoutUrl' => $session->url]);
        }

        if ($paymentMethod === 'ton') {
            $tonAddress = $_ENV['TON_WALLET'] ?? '';
            $tonAmount = $this->calculateTonAmount($items, $currency);
            $invoiceId = bin2hex(random_bytes(8));
            (new \App\Repositories\OrderRepository())->createFromCart($userId, 'ton', $items, $currency, $invoiceId, null, (float)$tonAmount, $shippingMethod, $shippingAddress);
            $tonPayload = [
                'to' => $tonAddress,
                'amountTon' => $tonAmount,
                'message' => 'Order ' . $invoiceId,
                'invoiceId' => $invoiceId,
            ];
            return $this->json($response, [ 'ton' => $tonPayload, 'note' => 'Send exact TON amount to the wallet with comment' ]);
        }

        return $this->json($response, ['error' => 'Unsupported payment method'], 400);
    }

    public function stripeWebhook(Request $request, Response $response): Response
    {
        $payload = (string)$request->getBody();
        $sig = $request->getHeaderLine('Stripe-Signature');
        $secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        if (empty($secret)) { return $response->withStatus(200); }
        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
        } catch (\Throwable $e) {
            return $response->withStatus(400);
        }
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            (new \App\Repositories\OrderRepository())->markPaidByStripeSession($session->id);
        }
        return $response->withStatus(200);
    }

    public function tonCallback(Request $request, Response $response, array $args): Response
    {
        $secret = $args['secret'] ?? '';
        if ($secret !== ($_ENV['TON_INVOICE_CALLBACK_SECRET'] ?? '')) {
            return $response->withStatus(403);
        }
        $data = $request->getParsedBody() ?? [];
        $invoiceId = $data['invoiceId'] ?? null;
        $txHash = $data['txHash'] ?? null;
        if ($invoiceId && $txHash) {
            (new \App\Repositories\OrderRepository())->markPaidByTonInvoice($invoiceId, $txHash);
        }
        return $response->withStatus(200);
    }

    private function calculateAmountCents(array $items, string $currency): int
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += ((float)$item['price'] * (int)$item['quantity']);
        }
        return (int) round($sum * 100);
    }

    private function calculateTonAmount(array $items, string $currency): string
    {
        $sumFiat = 0.0;
        foreach ($items as $item) {
            $sumFiat += ((float)$item['price'] * (int)$item['quantity']);
        }
        $rate = $this->fetchTonRate($currency);
        if ($rate <= 0) {
            $rate = 5.0; // fallback
        }
        $ton = $sumFiat / $rate;
        return number_format($ton, 4, '.', '');
    }

    private function fetchTonRate(string $currency): float
    {
        try {
            $base = rtrim($_ENV['TON_API_BASE'] ?? 'https://tonapi.io', '/');
            $res = $this->httpClient->get($base . '/v2/rates?tokens=ton&currencies=' . urlencode(strtolower($currency)), [ 'timeout' => 5 ]);
            $data = json_decode((string)$res->getBody(), true);
            $rate = $data['rates']['TON'][strtoupper($currency)] ?? null;
            return $rate ? (float)$rate : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}