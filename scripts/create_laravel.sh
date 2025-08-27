#!/usr/bin/env bash
set -euo pipefail

# Генерация Laravel приложения через контейнеризированный Composer (Sail образ)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$REPO_ROOT"

if [ -d "laravel" ]; then
  echo "Directory 'laravel' already exists. Aborting to avoid overwriting." >&2
  exit 1
fi

docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$PWD:/opt" -w /opt \
  laravelsail/php84-composer:latest \
  bash -lc "composer create-project laravel/laravel laravel && cd laravel && php artisan sail:install --with=mysql,redis && composer install && php artisan key:generate"

echo "Laravel app created in ./laravel. Configure .env and run docker compose as per docs/setup.md"

