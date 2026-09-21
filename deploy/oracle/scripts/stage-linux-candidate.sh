#!/usr/bin/env bash
set -euo pipefail

# Recibe un bundle fuente ya filtrado y construye/valida un artefacto Linux en
# staging. No crea base, no lee el entorno protegido y no inicia servicios.
if [[ "${EUID}" -ne 0 ]]; then
    echo 'Ejecuta el staging con sudo.' >&2
    exit 64
fi

source_archive="${1:-}"
[[ -r "${source_archive}" ]] || { echo 'Uso: sudo stage-linux-candidate.sh /ruta/bundle-fuente.tar.gz' >&2; exit 64; }

source_dir="$(mktemp -d /tmp/hubdigital-source.XXXXXX)"
build_dir="$(mktemp -d /tmp/hubdigital-build.XXXXXX)"
cleanup() { rm -rf -- "${source_dir}" "${build_dir}"; }
trap cleanup EXIT
tar --extract --gzip --file="${source_archive}" --directory="${source_dir}" --no-same-owner --no-same-permissions
[[ -f "${source_dir}/artisan" && -f "${source_dir}/vendor/autoload.php" ]] || { echo 'El bundle fuente no contiene runtime.' >&2; exit 65; }

if ! build_output="$(OUTPUT_DIR="${build_dir}/dist" PHP_BIN=/usr/bin/php8.4 COMPOSER_BIN=/usr/bin/composer \
    bash "${source_dir}/deploy/oracle/scripts/build-release.sh" 2>&1)"; then
    printf '%s\n' "${build_output}" | tail -n 40 >&2
    exit 1
fi
archive="$(printf '%s\n' "${build_output}" | tail -n 1)"
checksum="${archive}.sha256"
[[ -r "${archive}" && -r "${checksum}" ]] || { echo 'No se produjo candidato y suma.' >&2; exit 1; }
sha_expected="$(awk 'NR == 1 {print $1}' "${checksum}")"
sha_actual="$(sha256sum "${archive}" | awk '{print $1}')"
[[ "${sha_actual}" == "${sha_expected}" ]] || { echo 'Checksum del candidato invalido.' >&2; exit 1; }

release_id="${sha_actual:0:16}"
staging=/srv/hubdigital/staging
target_dir="${staging}/${release_id}"
[[ ! -e "${target_dir}" ]] || { echo 'El staging de esta release ya existe; no se sobrescribe.' >&2; exit 73; }
install -d -m 0755 "${target_dir}"
install -m 0644 "${archive}" "${staging}/$(basename "${archive}")"
install -m 0644 "${checksum}" "${staging}/$(basename "${checksum}")"
tar --extract --gzip --file="${archive}" --directory="${target_dir}" --no-same-owner --no-same-permissions
PHP_BIN=/usr/bin/php8.4 COMPOSER_BIN=/usr/bin/composer bash "${target_dir}/deploy/oracle/scripts/verify-platform.sh" "${target_dir}"
/usr/bin/php8.4 -l "${target_dir}/config/deposit-storage.php" >/dev/null
/usr/bin/php8.4 -l "${target_dir}/Modules/GestionPrestamosRecepciones/app/Infrastructure/Storage/AlmacenamientoDepositos.php" >/dev/null

printf 'candidate=%s\nchecksum=%s\nstaging=%s\nplatform=ok\n' "$(basename "${archive}")" "${sha_actual}" "${target_dir}"
