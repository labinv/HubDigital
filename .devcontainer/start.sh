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

r2_presentes=0
for variable in R2_ACCOUNT_ID R2_BUCKET R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY; do
    if [[ -n "${!variable:-}" ]]; then
        r2_presentes=$((r2_presentes + 1))
    fi
done
if [[ ${r2_presentes} -gt 0 && ${r2_presentes} -lt 4 ]]; then
    echo "ERROR: la configuracion R2 de Codespaces esta incompleta." >&2
    exit 1
fi
if [[ ${r2_presentes} -eq 4 ]]; then
    docker compose exec -T app php artisan depositos:verificar-almacenamiento --exigir-r2
else
    echo "R2 no configurado: se usa fallback local solo para esta sesion de desarrollo."
fi

if [[ -n "${CLOUDFLARE_TUNNEL_TOKEN:-}" ]]; then
    docker compose --profile tunnel up -d cloudflared
    echo "HubDigital disponible en https://dev.labinvepn.org"
else
    echo "HubDigital listo en el puerto 80 de Codespaces."
    echo "Agrega CLOUDFLARE_TUNNEL_TOKEN como secreto de Codespaces para habilitar dev.labinvepn.org."
fi

echo "Correo de pruebas disponible en el puerto 8025 (Mailpit)."
