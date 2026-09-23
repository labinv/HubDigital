#!/usr/bin/env bash
set -euo pipefail

# El scheduler nunca acompaña a la activación inicial. Este segundo paso exige
# una marca explícita en el entorno protegido tras revisar schedule:list y los
# efectos de cada tarea contra la base restaurada.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta la activacion de scheduler con sudo.' >&2
    exit 64
fi

release_id="${1:-}"
[[ "${release_id}" =~ ^[0-9a-f]{16}$ ]] || {
    echo 'Uso: sudo activate-scheduler.sh <id-de-16-hex>' >&2
    exit 64
}

release_dir="/srv/hubdigital/releases/${release_id}"
state_file="/var/lib/hubdigital/migration/release-${release_id}.json"
env_file=/etc/hubdigital/hubdigital.env
[[ -f "${release_dir}/artisan" && -r "${state_file}" && -r "${env_file}" ]] || {
    echo 'Falta release activa, estado protegido o entorno.' >&2
    exit 66
}
[[ "$(readlink -f /srv/hubdigital/current)" == "${release_dir}" ]] || {
    echo 'La release indicada no es la release local activa.' >&2
    exit 65
}
read_env() { sed -n "s/^$1=//p" "${env_file}" | tail -n 1; }
[[ "$(read_env HUBDIGITAL_VALIDATION_MODE)" == true && "$(read_env MAIL_MAILER)" == smtp && "$(read_env HUBDIGITAL_ALLOW_AUTH_EMAILS)" == true ]] || {
    echo 'El scheduler exige validacion activa y SMTP limitado a correos de alta.' >&2
    exit 65
}
validation_queue="$(read_env HUBDIGITAL_VALIDATION_QUEUE)"
[[ "${validation_queue}" =~ ^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$ && "${validation_queue}" != default ]] || {
    echo 'El scheduler exige HUBDIGITAL_VALIDATION_QUEUE dedicada y distinta de default.' >&2
    exit 65
}
[[ "$(read_env HUBDIGITAL_SCHEDULER_REVIEWED)" == true ]] || {
    echo 'HUBDIGITAL_SCHEDULER_REVIEWED=true debe registrarse tras revisar schedule:list y sus efectos.' >&2
    exit 65
}
systemctl is-active --quiet cloudflared-hubdigital.service && {
    echo 'El Tunnel debe permanecer detenido durante la validacion del scheduler.' >&2
    exit 65
}
systemctl is-active --quiet hubdigital-schedule.service && {
    echo 'El scheduler ya esta ejecutandose.' >&2
    exit 65
}
systemctl is-active --quiet hubdigital-schedule.timer && {
    echo 'El timer del scheduler ya esta activo.' >&2
    exit 65
}

"${release_dir}/deploy/oracle/scripts/verify-release-state.sh" "${release_id}" activo_local_validacion
systemd-run --quiet --wait --collect --pipe \
    --property=User=www-data --property=Group=www-data \
    --property=EnvironmentFile="${env_file}" --working-directory="${release_dir}" \
    /usr/bin/php8.4 artisan schedule:list --no-interaction
systemctl enable --now hubdigital-schedule.timer
systemctl is-active --quiet hubdigital-schedule.timer
jq --arg activated_at "$(date --utc +%FT%TZ)" \
    '. + {scheduler_activado_en:$activated_at, estado:"activo_local_con_scheduler"}' "${state_file}" > "${state_file}.tmp"
chmod 0600 "${state_file}.tmp"
mv "${state_file}.tmp" "${state_file}"
echo "Scheduler activo localmente para ${release_id}. El Tunnel sigue detenido."
