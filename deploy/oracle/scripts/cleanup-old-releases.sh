#!/usr/bin/env bash
set -euo pipefail

# Conserva current y la release activada valida inmediatamente anterior.
# No accede a PostgreSQL, R2, respaldos ni al entorno protegido.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecute la limpieza con sudo.' >&2
    exit 64
fi

mode="${1:---dry-run}"
case "${mode}" in
    --dry-run) apply=0 ;;
    --apply) apply=1 ;;
    *) echo 'Uso: sudo cleanup-old-releases.sh [--dry-run|--apply]' >&2; exit 64 ;;
esac

releases_root=/srv/hubdigital/releases
staging_root=/srv/hubdigital/staging
state_root=/var/lib/hubdigital/migration
current_link=/srv/hubdigital/current

for required_command in flock jq readlink sha256sum; do
    command -v "${required_command}" >/dev/null || {
        echo "Falta el comando requerido: ${required_command}." >&2
        exit 69
    }
done

install -d -m 0755 /run/hubdigital
exec 9>/run/hubdigital/retention.lock
flock -n 9 || { echo 'Ya existe otra limpieza de releases en ejecucion.' >&2; exit 75; }

current_dir="$(readlink -f "${current_link}" 2>/dev/null || true)"
case "${current_dir}" in
    "${releases_root}"/[0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f]) ;;
    *) echo "current no apunta a una release valida: ${current_dir:-sin destino}." >&2; exit 65 ;;
esac
[[ -d "${current_dir}" && -f "${current_dir}/artisan" ]] || {
    echo 'La release activa no contiene el runtime esperado.' >&2
    exit 66
}
current_id="${current_dir##*/}"

valid_activated_release() {
    local release_id="$1" release_dir state_file state_id state_status
    release_dir="${releases_root}/${release_id}"
    state_file="${state_root}/release-${release_id}.json"
    [[ -d "${release_dir}" && -f "${release_dir}/artisan" && -r "${state_file}" ]] || return 1
    state_id="$(jq -er '.release_id' "${state_file}" 2>/dev/null)" || return 1
    state_status="$(jq -er '.estado' "${state_file}" 2>/dev/null)" || return 1
    [[ "${state_id}" == "${release_id}" && "${state_status}" == activo_local_validacion ]] || return 1
    "${release_dir}/deploy/oracle/scripts/verify-release-state.sh" \
        "${release_id}" activo_local_validacion >/dev/null 2>&1
}

previous_id=''
previous_epoch=0
while IFS= read -r -d '' release_dir; do
    release_id="${release_dir##*/}"
    [[ "${release_id}" != "${current_id}" && "${release_id}" =~ ^[0-9a-f]{16}$ ]] || continue
    valid_activated_release "${release_id}" || continue
    state_file="${state_root}/release-${release_id}.json"
    activated_at="$(jq -er '.activado_local_en' "${state_file}" 2>/dev/null || true)"
    activated_epoch="$(date --date="${activated_at}" +%s 2>/dev/null || stat -c %Y "${state_file}")"
    if (( activated_epoch > previous_epoch )); then
        previous_epoch="${activated_epoch}"
        previous_id="${release_id}"
    fi
done < <(find "${releases_root}" -mindepth 1 -maxdepth 1 -type d -print0)

echo "Release activa conservada: ${current_id}"
if [[ -n "${previous_id}" ]]; then
    echo "Release anterior valida conservada: ${previous_id}"
else
    echo 'Release anterior valida conservada: ninguna disponible'
fi
if [[ "${apply}" -eq 0 ]]; then
    echo 'Modo: simulacion; no se eliminara nada.'
else
    echo 'Modo: aplicar politica de retencion.'
fi

removed=0
reclaimed_kib=0
remove_target() {
    local target="$1" kind="$2" size_kib action
    [[ -e "${target}" || -L "${target}" ]] || return 0
    size_kib="$(du -sk -- "${target}" 2>/dev/null | awk '{print $1}')"
    size_kib="${size_kib:-0}"
    if [[ "${apply}" -eq 1 ]]; then action=ELIMINAR; else action=ELIMINARIA; fi
    printf '%s %s: %s (%s KiB)\n' "${action}" "${kind}" "${target}" "${size_kib}"
    if [[ "${apply}" -eq 1 ]]; then rm -rf -- "${target}"; fi
    removed=$((removed + 1))
    reclaimed_kib=$((reclaimed_kib + size_kib))
}

while IFS= read -r -d '' release_dir; do
    release_id="${release_dir##*/}"
    [[ "${release_id}" =~ ^[0-9a-f]{16}$ ]] || {
        echo "OMITIDO directorio inesperado en releases: ${release_dir}" >&2
        continue
    }
    [[ "${release_id}" != "${current_id}" && "${release_id}" != "${previous_id}" ]] || continue
    remove_target "${release_dir}" 'release antigua o candidata'
    remove_target "${state_root}/release-${release_id}.json" 'estado de release eliminada'
done < <(find "${releases_root}" -mindepth 1 -maxdepth 1 -type d -print0)

while IFS= read -r -d '' invalid_release; do
    case "${invalid_release}" in
        /srv/hubdigital/release-invalida-[0-9a-f]*) remove_target "${invalid_release}" 'release fallida' ;;
        *) echo "OMITIDO destino fallido inesperado: ${invalid_release}" >&2 ;;
    esac
done < <(find /srv/hubdigital -mindepth 1 -maxdepth 1 -name 'release-invalida-*' -print0)

while IFS= read -r -d '' staging_item; do
    case "${staging_item}" in
        "${staging_root}"/*) remove_target "${staging_item}" 'staging antiguo' ;;
        *) echo "OMITIDO staging inesperado: ${staging_item}" >&2 ;;
    esac
done < <(find "${staging_root}" -mindepth 1 -maxdepth 1 -print0)

printf 'Resumen: %d destinos; aproximadamente %d MiB.\n' \
    "${removed}" "$(( (reclaimed_kib + 1023) / 1024 ))"
if [[ "${apply}" -eq 0 ]]; then
    echo 'Simulacion terminada. Para aplicar: vuelva a ejecutar con --apply.'
else
    echo 'Politica de retencion aplicada correctamente.'
    df -h /srv/hubdigital | tail -n 1
fi
