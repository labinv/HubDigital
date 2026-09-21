#!/usr/bin/env bash
set -euo pipefail

# Registra la latencia observada de una interacción real (navegador, login,
# Livewire o cola) junto a la muestra de recursos. No contiene credenciales,
# cuerpos HTTP ni nombres de documentos.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta el registro con sudo.' >&2
    exit 64
fi

label="${1:-}"
scenario="${2:-}"
response_ms="${3:-}"
result="${4:-}"
note="${5:-}"
[[ "${label}" =~ ^[A-Za-z0-9._-]+$ ]] || { echo 'Etiqueta invalida.' >&2; exit 64; }
[[ "${scenario}" =~ ^[A-Za-z0-9._-]+$ ]] || { echo 'Escenario invalido.' >&2; exit 64; }
[[ "${response_ms}" =~ ^[0-9]+([.][0-9]+)?$ ]] || { echo 'La latencia debe estar en milisegundos.' >&2; exit 64; }
[[ "${result}" == ok || "${result}" == error ]] || { echo 'El resultado debe ser ok o error.' >&2; exit 64; }

output_dir=/var/lib/hubdigital/measurements
output="${output_dir}/observations.csv"
install -d -m 0700 "${output_dir}"
if [[ ! -e "${output}" ]]; then
    printf 'timestamp_utc,label,scenario,response_ms,result,note\n' > "${output}"
fi

# Mantiene el CSV legible sin admitir saltos de linea ni comas que alteren sus
# columnas. Usar IDs técnicos o códigos HTTP, nunca datos personales.
note="${note//$'\n'/ }"
note="${note//,/;}"
printf '%s,%s,%s,%s,%s,%s\n' "$(date --utc +%FT%TZ)" "${label}" "${scenario}" "${response_ms}" "${result}" "${note}" >> "${output}"
chmod 0600 "${output}"
printf '%s\n' "${output}"
