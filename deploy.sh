#!/usr/bin/env bash
set -e

echo "==> アプリのディレクトリへ移動"
cd /var/www/ticket-app

echo "==> 最新のコードを取得 (git pull origin main)"
git pull origin main

echo "==> PHP の依存をインストール (composer install)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> フロントエンドの依存をインストールしてビルド (npm ci && npm run build)"
npm ci && npm run build

echo "==> マイグレーションを実行 (migrate --force)"
php artisan migrate --force

echo "==> キャッシュを作成 (optimize)"
php artisan optimize

echo "==> デプロイ完了"
