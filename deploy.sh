#!/usr/bin/env bash
set -euo pipefail

cd /var/www/blog

echo "→ Récupération du code"
git pull --ff-only

echo "→ Dépendances"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Base de données"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "→ Assets"
php bin/console importmap:install
php bin/console tailwind:build --minify
php bin/console asset-map:compile

echo "→ Cache"
php bin/console cache:clear

echo "→ Redémarrage du worker"
sudo supervisorctl restart blog-scheduler

echo "✓ Déploiement terminé"
