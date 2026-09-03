#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
    cp .devcontainer/codespaces.env .env
    app_key="$(openssl rand -base64 32 | tr -d '\n')"
    sed -i "s|^APP_KEY=.*|APP_KEY=base64:${app_key}|" .env
fi

build_flag="--build"
if [[ "${1:-}" == "--no-build" ]]; then
    build_flag=""
fi

docker compose --profile development up -d ${build_flag} postgres mailpit app worker scheduler nginx
docker compose exec -T app php artisan migrate --force

if [[ -n "${CLOUDFLARE_TUNNEL_TOKEN:-}" ]]; then
    docker compose --profile tunnel up -d cloudflared
    echo "HubDigital disponible en https://dev.labinvepn.org"
else
    echo "HubDigital listo en el puerto 80 de Codespaces."
    echo "Agrega CLOUDFLARE_TUNNEL_TOKEN como secreto de Codespaces para habilitar dev.labinvepn.org."
fi

echo "Correo de pruebas disponible en el puerto 8025 (Mailpit)."
