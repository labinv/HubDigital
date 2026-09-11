#!/usr/bin/env sh
set -eu

: "${COMPOSE_FILE:?Falta COMPOSE_FILE}"
: "${COMPOSE_PROJECT:?Falta COMPOSE_PROJECT aislado}"
: "${BACKUP_DIR:?Falta BACKUP_DIR}"
: "${RESTORE_STORAGE:?Falta RESTORE_STORAGE vacio}"
: "${CONFIRMAR_DESTINO_AISLADO:?Debe valer SI}"
[ "$CONFIRMAR_DESTINO_AISLADO" = SI ] || { echo "Destino aislado no confirmado." >&2; exit 4; }
[ -d "$RESTORE_STORAGE" ] && [ -n "$(find "$RESTORE_STORAGE" -mindepth 1 -print -quit)" ] && { echo "Storage destino no vacio." >&2; exit 4; }

umask 077
manifesto="$BACKUP_DIR/manifiesto-coordinado.json"
test -s "$manifesto"
dc() { docker compose -p "$COMPOSE_PROJECT" -f "$COMPOSE_FILE" "$@"; }
dc run --rm --no-deps --user "0:0" --entrypoint php -v "$BACKUP_DIR:/respaldo:ro" app \
    -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); if(($m["version_formato"]??null)!==1||($m["estado"]??null)!=="COMPLETO") exit(4);' \
    /respaldo/manifiesto-coordinado.json
dump_sha="$(dc run --rm --no-deps --user "0:0" --entrypoint php -v "$BACKUP_DIR:/respaldo:ro" app \
    -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["postgresql"]["sha256"];' \
    /respaldo/manifiesto-coordinado.json)"
docs_sha="$(dc run --rm --no-deps --user "0:0" --entrypoint php -v "$BACKUP_DIR:/respaldo:ro" app \
    -r '$m=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); echo $m["documentos"]["sha256"];' \
    /respaldo/manifiesto-coordinado.json)"
[ "$(sha256sum "$BACKUP_DIR/postgresql.dump" | cut -d ' ' -f 1)" = "$dump_sha" ] || exit 4
[ "$(sha256sum "$BACKUP_DIR/manifiesto-documentos.json" | cut -d ' ' -f 1)" = "$docs_sha" ] || exit 4

dc up -d postgres
until dc exec -T postgres sh -c 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null; do sleep 2; done
dc exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges' <"$BACKUP_DIR/postgresql.dump"
mkdir -p "$RESTORE_STORAGE"
dc run --rm --no-deps -v "$BACKUP_DIR:/respaldo:ro" -v "$RESTORE_STORAGE:/restaurado" app \
    php artisan depositos:restaurar-documentos --manifiesto=/respaldo/manifiesto-documentos.json --directorio-destino=/restaurado
# La restauracion se ejecuta como root en el contenedor efimero. PHP-FPM sirve
# los documentos como www-data (UID/GID 33), por lo que se normalizan propietario
# y permisos antes de exponer el entorno restaurado.
dc run --rm --no-deps -v "$RESTORE_STORAGE:/restaurado" app sh -c \
    'chown -R 33:33 /restaurado && find /restaurado -type d -exec chmod 0750 {} \; && find /restaurado -type f -exec chmod 0640 {} \;'
dc up -d app worker scheduler nginx
