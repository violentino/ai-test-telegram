install:
	composer install

run:
	php -S 0.0.0.0:8080 -t public

migrate:
	mysql -h $${DB_HOST} -P $${DB_PORT} -u $${DB_USERNAME} -p$${DB_PASSWORD} $${DB_DATABASE} < database/migrations.sql

webhook:
	curl -X POST "https://api.telegram.org/bot$${TELEGRAM_BOT_TOKEN}/setWebhook" -H "Content-Type: application/json" -d '{"url":"'$${APP_URL}'/webhook/'$${TELEGRAM_WEBHOOK_SECRET}'"}'