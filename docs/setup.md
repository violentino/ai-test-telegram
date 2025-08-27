## Сборка и запуск: Laravel + Docker + Python Analysis

### Предпосылки
- Docker Engine + Docker Compose v2
- Git

Если локально нет PHP/Composer — это не требуется. Всё делаем через Docker.

### Структура репозитория (целевое)
```
.
├─ laravel/                 # Приложение Laravel (Sail)
├─ analysis-service/        # Python FastAPI сервис
├─ docker/                  # Общие docker файлы (опционально)
├─ docs/
│  ├─ architecture.md
│  └─ setup.md
└─ docker-compose.yml       # Оркестрация
```

### Шаг 1. Инициализация Laravel (через Sail-образ)
1) Создать папку `laravel` и сгенерировать проект внутри контейнера:
```bash
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$PWD:/opt" -w /opt \
  laravelsail/php84-composer:latest \
  bash -lc "composer create-project laravel/laravel laravel && cd laravel && php artisan sail:install --with=mysql,redis"
```

2) В `laravel/.env` выставить:
```
APP_NAME="MarketBot"
APP_ENV=local
APP_KEY=
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=marketbot
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_WEBHOOK_SECRET=

ANALYSIS_BASE_URL=http://analysis-service:8000

TON_API_KEY=
TON_WALLET_ADDRESS=
TON_PRICE_PER_REQUEST_USD=1
```

3) Опционально подключить Horizon для очередей позже.

### Шаг 2. Подготовка Python Analysis Service
1) Создайте каркас:
```bash
mkdir -p analysis-service
cat > analysis-service/requirements.txt << 'EOF'
fastapi==0.115.0
uvicorn[standard]==0.30.6
numpy==2.1.1
pandas==2.2.2
yfinance==0.2.43
matplotlib==3.9.2
scipy==1.14.1
scikit-learn==1.5.2
EOF

cat > analysis-service/main.py << 'EOF'
from fastapi import FastAPI
from pydantic import BaseModel
import base64

app = FastAPI()

class AnalyzeRequest(BaseModel):
    ticker: str
    period: str = "1y"
    interval: str = "1d"

@app.get("/health")
def health():
    return {"status": "ok"}

@app.post("/analyze")
def analyze(req: AnalyzeRequest):
    # TODO: Реальная аналитика. Пока заглушка.
    dummy_png = base64.b64encode(b"PNG").decode()
    return {
        "image_base64": dummy_png,
        "levels": [],
        "trendlines": [],
        "signals": [],
        "meta": {"ticker": req.ticker, "period": req.period, "interval": req.interval},
    }
EOF

cat > analysis-service/Dockerfile << 'EOF'
FROM python:3.11-slim
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    && rm -rf /var/lib/apt/lists/*
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt
COPY . .
EXPOSE 8000
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
EOF
```

### Шаг 3. docker-compose.yml
Создайте в корне `docker-compose.yml`:
```yaml
version: "3.9"
services:
  laravel:
    build: ./laravel
    command: bash -lc "php artisan serve --host=0.0.0.0 --port=8000"
    ports:
      - "8001:8000"
    volumes:
      - ./laravel:/var/www/html
    depends_on:
      - mysql
      - redis
      - analysis-service

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: marketbot
      MYSQL_USER: sail
      MYSQL_PASSWORD: password
      MYSQL_ROOT_PASSWORD: root
    ports:
      - "33060:3306"
    volumes:
      - db_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

  analysis-service:
    build: ./analysis-service
    ports:
      - "8000:8000"

volumes:
  db_data:
```

Примечание: В проде используйте Sail команду `./vendor/bin/sail up -d` и соответствующий `docker-compose.yml`, который генератор создаст в `laravel`.

### Шаг 4. Установка зависимостей Laravel через контейнер Composer
Если Composer не установлен локально, используйте контейнер:
```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/laravel:/app" -w /app \
  laravelsail/php84-composer:latest bash -lc "composer install && php artisan key:generate"
```

### Шаг 5. Запуск
```bash
docker compose up --build
```

- Laravel: `http://localhost:8001`
- Analysis: `http://localhost:8000/health`

### Шаг 6. Настройка Telegram Webhook
1) Получите токен у `@BotFather`
2) Установите переменные в `laravel/.env`: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_WEBHOOK_SECRET`
3) Пропишите маршрут вебхука в Laravel (будет добавлен при интеграции)
4) Установите вебхук:
```bash
curl -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/setWebhook" \
  -H 'Content-Type: application/json' \
  -d '{"url": "https://YOUR_DOMAIN/telegram/webhook?secret=YOUR_SECRET"}'
```

### Шаг 7. Платежи TON
- Рекомендуется Wallet Pay (инвойсы) или `tonapi.io` для трекинга входящих платежей на кошелёк
- Настройте `TON_API_KEY`, `TON_WALLET_ADDRESS`, `TON_PRICE_PER_REQUEST_USD`

### Дальнейшие шаги
- Добавить контроллер Telegram, интеграцию SDK
- Реализовать платёжный флоу TON
- Соединить Laravel и Python сервис
- Добавить очереди и кэш

