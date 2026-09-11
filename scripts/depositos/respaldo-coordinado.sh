#!/usr/bin/env sh
set -eu

# Variables obligatorias: COMPOSE_FILE, COMPOSE_PROJECT, BACKUP_ROOT,
# BACKUP_ID, BACKUP_PREFIX. PGUSER y PGDATABASE se leen del entorno del
# contenedor PostgreSQL; las credenciales nunca se imprimen.
: "${COMPOSE_FILE:?Falta COMPOSE_FILE}"
: "${COMPOSE_PROJECT:?Falta COMPOSE_PROJECT}"
: "${BACKUP_ROOT:?Falta BACKUP_ROOT}"
: "${BACKUP_ID:?Falta BACKUP_ID}"
: "${BACKUP_PREFIX:?Falta BACKUP_PREFIX}"

umask 077
destino="$BACKUP_ROOT/$BACKUP_ID"
lock="$BACKUP_ROOT/.respaldo-depositos.lock"
mkdir -p "$BACKUP_ROOT"

if ! mkdir "$lock" 2>/dev/null; then
    if [ -f "$lock/pid" ] && kill -0 "$(cat "$lock/pid")" 2>/dev/null; then
        echo "Ya existe un respaldo coordinado activo." >&2
        exit 4
    fi
    mv "$lock" "$lock.abandonado.$(date -u +%Y%m%dT%H%M%SZ)"
    mkdir "$lock"
fi
echo "$$" >"$lock/pid"
echo "$(date -u +%FT%TZ)" >"$lock/iniciado-en"
restaurar_servicios=0
limpiar_y_recuperar() {
    estado=$?
    if [ "$restaurar_servicios" -eq 1 ]; then
        dc start app worker scheduler nginx >/dev/null 2>&1 || true
        dc exec -T app php artisan up >/dev/null 2>&1 || true
    fi
    rm -f "$lock/pid" "$lock/iniciado-en"
    rmdir "$lock" 2>/dev/null || true
    exit "$estado"
}
trap limpiar_y_recuperar EXIT
trap 'exit 130' HUP INT TERM

mkdir -p "$destino"
manifesto_documentos="$destino/manifiesto-documentos.json"
manifesto="$destino/manifiesto-coordinado.json"
dump="$destino/postgresql.dump"

dc() { docker compose -p "$COMPOSE_PROJECT" -f "$COMPOSE_FILE" "$@"; }

# Barrera de escritura: tráfico, workers y scheduler quedan detenidos. El
# contenedor efímero posterior solo ejecuta la copia documental.
restaurar_servicios=1
dc exec -T app php artisan down --retry=60 >/dev/null
dc stop nginx worker scheduler >/dev/null

reanudar=""
[ -f "$manifesto_documentos" ] && reanudar="--reanudar"
dc run --rm --no-deps -v "$destino:/evidencia" app php artisan depositos:respaldar-documentos \
    --id="$BACKUP_ID" \
    --prefijo-destino="$BACKUP_PREFIX" \
    --salida-manifiesto="/evidencia/manifiesto-documentos.json" \
    $reanudar
test -s "$manifesto_documentos"

dc exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' >"$dump"
test -s "$dump"
dump_sha="$(sha256sum "$dump" | cut -d ' ' -f 1)"
docs_sha="$(sha256sum "$manifesto_documentos" | cut -d ' ' -f 1)"
jobs="$(dc exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from jobs"')"
failed_jobs="$(dc exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from failed_jobs"')"

cat >"$manifesto" <<EOF
{"version_formato":1,"id":"$BACKUP_ID","creado_en":"$(date -u +%FT%TZ)","estado":"COMPLETO","postgresql":{"archivo":"postgresql.dump","sha256":"$dump_sha"},"documentos":{"archivo":"manifiesto-documentos.json","sha256":"$docs_sha","prefijo":"$BACKUP_PREFIX"},"colas":{"pendientes":$jobs,"fallidos":$failed_jobs}}
EOF
chmod 600 "$destino"/*

dc start app worker scheduler nginx >/dev/null
dc exec -T app php artisan up >/dev/null
restaurar_servicios=0
printf '%s\n' "$manifesto"
