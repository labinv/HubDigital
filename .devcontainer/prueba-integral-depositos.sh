#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan depositos:verificar-almacenamiento --exigir-r2

if docker compose exec -T app test -x vendor/bin/pest; then
  docker compose exec -T app php artisan test \
    tests/Unit/R2S3ClientTest.php \
    tests/Feature/AlmacenamientoDepositosTest.php \
    tests/Feature/FlujoDepositoE2ETest.php
else
  echo "La imagen de ejecucion no contiene dependencias dev; las pruebas Pest se ejecutan en GitHub Actions."
fi

echo "Prueba integral automatizada de depositos completada correctamente."
