<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use GuzzleHttp\Client;

class TelegramController
{
    private Client $httpClient;
    private string $botToken;

    public function __construct()
    {
        $this->httpClient = new Client(['base_uri' => 'https://api.telegram.org']);
        $this->botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
    }

    public function handle(Request $request, Response $response, array $args): Response
    {
        $secret = $args['secret'] ?? '';
        if (($secret !== ($_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '')) || $this->botToken === '') {
            $response->getBody()->write('forbidden');
            return $response->withStatus(403);
        }

        $update = $request->getParsedBody();
        if (!$update) {
            return $response->withStatus(400);
        }

        $this->processUpdate($update);

        $response->getBody()->write('ok');
        return $response;
    }

    private function processUpdate(array $update): void
    {
        $message = $update['message'] ?? $update['edited_message'] ?? null;
        $callbackQuery = $update['callback_query'] ?? null;
        $webAppData = $message['web_app_data'] ?? null;

        if ($message) {
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';

            if (preg_match('/^\/(start|menu)/', $text)) {
                $this->sendWebAppEntry($chatId);
                return;
            }

            if (!empty($webAppData)) {
                // Handle data returned from WebApp (e.g., checkout payload)
                $this->sendMessage($chatId, 'Received your order, processing...');
                return;
            }

            $this->sendMessage($chatId, 'Welcome! Tap the button to open the shop.');
            $this->sendWebAppEntry($chatId);
            return;
        }

        if ($callbackQuery) {
            $chatId = $callbackQuery['message']['chat']['id'];
            $this->sendMessage($chatId, 'Use the WebApp to browse and order.');
        }
    }

    private function sendMessage(int|string $chatId, string $text): void
    {
        $this->httpClient->post("/bot{$this->botToken}/sendMessage", [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ],
            'timeout' => 10,
        ]);
    }

    private function sendWebAppEntry(int|string $chatId): void
    {
        $webAppUrl = $_ENV['TELEGRAM_WEBAPP_URL'] ?? '';
        $this->httpClient->post("/bot{$this->botToken}/sendMessage", [
            'json' => [
                'chat_id' => $chatId,
                'text' => 'Open the shop:',
                'reply_markup' => [
                    'inline_keyboard' => [[[
                        'text' => 'Open Store',
                        'web_app' => ['url' => $webAppUrl]
                    ]]]
                ]
            ]
        ]);
    }
}