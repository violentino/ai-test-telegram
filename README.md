# Telegram Clothing Store Bot (PHP + Slim)

Features:
- Product catalog (categories, sizes, colors)
- Telegram WebApp UI
- Cart and checkout
- Payments: Card (Stripe) and TON wallet
- Dockerized (PHP 8.2 + MySQL 8)

## Quick start

1. Copy env
```
cp .env.example .env
```

2. Fill `.env` with your values:
- `TELEGRAM_BOT_TOKEN` and `TELEGRAM_WEBHOOK_SECRET`
- `TELEGRAM_WEBAPP_URL` (e.g. https://your-domain/webapp)
- `STRIPE_SECRET_KEY` and webhook secret (optional if you only use TON)
- `TON_WALLET` (already set) and `TON_INVOICE_CALLBACK_SECRET`
- `ADMIN_API_TOKEN` to protect admin endpoints

3. Run with Docker (recommended) or locally
- Docker: `docker compose up --build`
- Local: install PHP 8.2+, MySQL, and Composer; then:
```
composer install
php -S 0.0.0.0:8080 -t public
```

4. Create database and run migrations
- Create DB `ton_store` and user from `.env`
- Run migrations:
```
make migrate
```

5. Install Telegram webhook
- Publicly expose the app (use a domain + HTTPS or `ngrok http 8080`)
- Set webhook:
```
curl -X POST "https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook" \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://YOUR_DOMAIN/webhook/WEBHOOK_SECRET"}'
```

6. Open the bot and press "Open Store". The WebApp is served at `/webapp`.

## Payments

- Card: Stripe Checkout is used. Set `STRIPE_SECRET_KEY`. Update `success_url` and `cancel_url` if needed. Configure webhook to `/webhooks/stripe` and set `STRIPE_WEBHOOK_SECRET`.
- TON: The WebApp generates a transfer link to `TON_WALLET` with comment `Order <invoiceId>`. Reconcile manually by watching your wallet or implement callbacks to `/webhooks/ton/<TON_INVOICE_CALLBACK_SECRET>` posting `{ invoiceId, txHash }` to auto-mark as paid.

## Database schema

See `database/migrations.sql`. Tables: `products`, `product_images`, `product_variants`, `carts`, `cart_items`, `orders`, `order_items`.

## Admin
- Endpoints under `/admin` guarded with `Authorization: Bearer <ADMIN_API_TOKEN>`.
- Create product:
```
POST /admin/product
Authorization: Bearer <token>
{
  "name":"Sneakers", "description":"...", "category":"sneakers", "gender":"men", "price":79.99, "currency":"USD",
  "images":["https://.../1.jpg","https://.../2.jpg"],
  "variants":[{"size":"41","color":"black","stock":10},{"size":"42","color":"white","stock":5}]
}
```

## Deploy
- Build Docker image and run behind nginx with HTTPS
- Point Telegram webhook to `https://your-domain/webhook/<TELEGRAM_WEBHOOK_SECRET>`
- Ensure Stripe webhooks point to `/webhooks/stripe`