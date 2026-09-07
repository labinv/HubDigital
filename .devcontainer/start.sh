#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

# Los comandos post-create reciben estos valores automáticamente. Las sesiones
# SSH de mantenimiento pueden no exportarlos, aunque Codespaces conserva su
# archivo de entorno. Cargarlo permite reinicios reproducibles sin revelar ni
# persistir secretos en el repositorio.
codespaces_secret_env="/workspaces/.codespaces/shared/.env-secrets"
if [[ -f "${codespaces_secret_env}" ]]; then
    set -a
    # shellcheck disable=SC1090
    source "${codespaces_secret_env}"
    set +a

    # Los secretos de este Codespace fueron cargados originalmente como Base64
    # para evitar interpretación del shell. Se reconocen por el valor conocido
    # y no sensible de COMPOSE_PROJECT_NAME; la decodificación ocurre solo en
    # memoria y nunca se imprime.
    if [[ "${COMPOSE_PROJECT_NAME:-}" == "aHViZGlnaXRhbC1kZXY=" ]]; then
        for clave_codificada in CLOUDFLARE_TUNNEL_TOKEN DEPOSIT_STORAGE_DRIVER R2_ACCOUNT_ID R2_BUCKET R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY R2_ENDPOINT VAPID_PUBLIC_KEY VAPID_PRIVATE_KEY; do
            valor_codificado="${!clave_codificada:-}"
            [[ -n "${valor_codificado}" ]] || continue
            valor_decodificado="$(printf '%s' "${valor_codificado}" | base64 --decode)"
            printf -v "${clave_codificada}" '%s' "${valor_decodificado}"
            export "${clave_codificada}"
        done
    fi

    export COMPOSE_PROJECT_NAME=hubdigital-dev
fi

if [[ ! -f .env ]]; then
    cp .devcontainer/codespaces.env .env
    app_key="$(openssl rand -base64 32 | tr -d '\n')"
    sed -i "s|^APP_KEY=.*|APP_KEY=base64:${app_key}|" .env
fi

# Codespaces entrega secretos como variables del proceso; Docker Compose, en
# cambio, consume el archivo .env. Copiamos solo la lista permitida y nunca la
# mostramos en consola. La configuración local que no tiene secretos conserva
# su fallback para permitir desarrollo sin infraestructura remota.
actualizar_env_secreto() {
    local clave="$1"
    local valor="${!clave:-}"

    [[ -n "${valor}" ]] || return 0

    local temporal
    temporal="$(mktemp)"
    awk -v clave="${clave}" -v valor="${valor}" '
        index($0, clave "=") == 1 { print clave "=" valor; visto = 1; next }
        { print }
        END { if (!visto) print clave "=" valor }
    ' .env > "${temporal}"
    mv "${temporal}" .env
}

for secreto in R2_ACCOUNT_ID R2_BUCKET R2_ACCESS_KEY_ID R2_SECRET_ACCESS_KEY R2_ENDPOINT VAPID_SUBJECT VAPID_PUBLIC_KEY VAPID_PRIVATE_KEY; do
    actualizar_env_secreto "${secreto}"
done

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
    sed -i 's|^DEPOSIT_STORAGE_DRIVER=.*|DEPOSIT_STORAGE_DRIVER=r2|' .env
    sed -i 's|^DEPOSIT_STORAGE_REQUIRE_REMOTE=.*|DEPOSIT_STORAGE_REQUIRE_REMOTE=true|' .env
elif [[ -n "${CODESPACES:-}" ]]; then
    echo "ERROR: Codespaces requiere los cuatro secretos R2 para evitar almacenamiento local silencioso." >&2
    exit 1
else
    sed -i 's|^DEPOSIT_STORAGE_DRIVER=.*|DEPOSIT_STORAGE_DRIVER=auto|' .env
    sed -i 's|^DEPOSIT_STORAGE_REQUIRE_REMOTE=.*|DEPOSIT_STORAGE_REQUIRE_REMOTE=false|' .env
fi

build_flag="--build"
if [[ "${1:-}" == "--no-build" ]]; then
    build_flag=""
fi

docker compose --profile development up -d ${build_flag} postgres mailpit app worker scheduler nginx
docker compose exec -T app php artisan migrate --force

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
