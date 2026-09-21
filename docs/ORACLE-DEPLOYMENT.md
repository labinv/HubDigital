# HubDigital en Oracle Always Free

Estado operativo al 21 de septiembre de 2026. HubDigital está instalado de
forma nativa en la VM `labinvepn-dev-vnic` (`129.153.23.57`), sin Docker, sin
swap y sin depender de Codespaces, Windows o Cloudflare Tunnel. La aplicación
está publicada en `https://dev.labinvepn.org` mediante Cloudflare con el origen
OCI accesible directamente por HTTPS.

## Arquitectura vigente

- Ubuntu y paquetes nativos: Nginx, PHP-FPM 8.4 y PostgreSQL 16.
- Base nueva `hubdigital_pruebas`, rol de aplicación
  `hubdigital_pruebas_app`, 129 migraciones aplicadas y ninguna pendiente.
- Un worker serializado de la cola dedicada `validation`.
- Un timer de systemd que ejecuta `schedule:run` una vez por minuto.
- Documentos persistentes exclusivamente en R2, bucket
  `labinvepn-depositos-desarrollo`, prefijo `pruebas-oracle-20260920`.
- PostgreSQL contiene datos estructurados, metadatos y referencias de objetos;
  no se usa como almacén de documentos.
- Temporales de subida, OCR, PDF y firma bajo el tmpfs dedicado
  `/run/hubdigital` de 192 MiB; no sobreviven a un reinicio.
- Correo en `log` y `HUBDIGITAL_VALIDATION_MODE=true` durante la validación.
- `cloudflared-hubdigital.service` está deshabilitado e inactivo. Su unidad
  heredada puede existir en el host, pero no forma parte del despliegue.

El entorno secreto está en `/etc/hubdigital/hubdigital.env`, fuera de cada
release y con acceso restringido. Nunca debe copiarse al repositorio, al
artefacto ni a este documento.

## Release activa y trazabilidad

- Release: `af678306a33ae2c0`.
- Artefacto: `hubdigital-sin-git-0118f873fbcb.tar.gz`.
- SHA-256 del artefacto:
  `af678306a33ae2c041d12235c1a59577553053616bd81a531197d5c9ce30fe91`.
- SHA-256 del contenido/manifiesto:
  `0118f873fbcb9e3a6e81aa1b93cb84e6b6f4e465f5c6eceebdf2fcda9374743f`.
- Enlace activo: `/srv/hubdigital/current`.
- Estado protegido:
  `/var/lib/hubdigital/migration/release-af678306a33ae2c0.json`.

El artefacto excluye `.git`, `.env`, pruebas de módulos, fuentes de Node y
otros archivos ajenos al runtime. `build-release.sh` genera manifiesto y
metadatos reproducibles; `stage-linux-candidate.sh` vuelve a verificar el
checksum, rutas, enlaces, plataforma y permisos POSIX antes de instalar.

## Servicios y límites

| Componente | Configuración | Límite systemd |
| --- | --- | --- |
| PostgreSQL 16 | sólo `127.0.0.1:5432`, 20 conexiones, `shared_buffers=96MB`, `work_mem=2MB` | 224 MiB |
| PHP-FPM 8.4 | hasta 2 procesos, `memory_limit=192M` | 256 MiB |
| Worker | uno, cola `validation`, `--timeout=300`, `--memory=192`, `--max-time=900` | 256 MiB |
| Scheduler | un `schedule:run` por minuto | 80 MiB |
| Nginx | HTTP 80 durante bootstrap; HTTPS 443 después del certificado | 64 MiB |

El reinicio periódico del worker al alcanzar `--max-time=900` es deliberado;
systemd lo levanta de nuevo. PostgreSQL no se expone en la red pública.

## Comprobaciones realizadas

La release pasó:

- requisitos de PHP/Composer y sintaxis de configuración;
- las 129 migraciones con estado `Ran` y ninguna migración pendiente;
- escritura, lectura, comparación SHA-256, borrado y confirmación de ausencia
  sobre R2 real, sin fallback local;
- limpieza normal, por error y de huérfanos en tmpfs;
- `nginx -t`, petición local a `/login`, PHP-FPM, worker y scheduler;
- revisión y ejecución manual de
  `solicitudes:limpiar-borradores` y
  `prestamos:evaluar-plazos-devolucion`;
- ausencia de trabajos pendientes/fallidos restaurados.

Medición final con 30 peticiones HTTP locales, una prueba completa de R2 y un
tick del scheduler:

- archivo:
  `/var/lib/hubdigital/measurements/20260920T204143Z-active-http-r2-final.csv`;
- duración solicitada/real: 45/45 segundos;
- memoria mínima disponible: 387340 KiB (aprox. 378 MiB);
- swap máximo: 0 KiB;
- reinicios durante la muestra: 0 en todos los servicios;
- eventos OOM/cgroup: ninguno;
- máximos `MemoryCurrent`: PostgreSQL 52.3 MiB, PHP-FPM 46.2 MiB,
  worker 55.5 MiB y Nginx 3.0 MiB;
- PHP-FPM: 31 peticiones desde la activación, 0 lentas.

## R2

La aplicación usa una credencial dedicada denominada
`hubdigital-oracle-dev-r2-20260920`, limitada al bucket de desarrollo y al
permiso de objetos. La clave secreta sólo está en el entorno protegido.

Comprobación manual, ejecutada como el mismo usuario del servicio:

```bash
sudo systemd-run --quiet --wait --collect --pipe \
  --property=User=www-data --property=Group=www-data \
  --property=EnvironmentFile=/etc/hubdigital/hubdigital.env \
  --working-directory=/srv/hubdigital/current \
  /usr/bin/php8.4 artisan depositos:verificar-almacenamiento --exigir-r2
```

Debe terminar indicando que escritura, lectura, integridad y borrado fueron
correctos. Cualquier fallback a disco local es un error de despliegue.

## DNS y HTTPS directo

La VNIC no tiene NSG. Su Security List efectiva conserva SSH y acepta reglas
**stateful** TCP 80 y 443 desde `0.0.0.0/0`; no se abrió 5432. PostgreSQL sigue
escuchando sólo en `127.0.0.1:5432` y el firewall del sistema permite 22/80/443
antes de su rechazo final.

Se sustituyó exclusivamente el registro heredado de `dev.labinvepn.org` por un
registro `A` a `129.153.23.57`. El proxy está activo y Cloudflare usa SSL/TLS
`Full (strict)`. El DNS anterior permanece en el respaldo protegido.

El certificado Let's Encrypt del origen se emitió con HTTP-01:

```bash
sudo certbot certonly --webroot -w /var/www/html \
  -d dev.labinvepn.org --non-interactive --agree-tos \
  --register-unsafely-without-email
sudo /srv/hubdigital/current/deploy/oracle/scripts/install-https-config.sh
```

La renovación automática está habilitada y fue comprobada:

```bash
sudo systemctl enable --now certbot.timer
sudo certbot renew --dry-run
```

`certbot renew --dry-run` terminó correctamente; el certificado vigente vence
el 19 de diciembre de 2026. La configuración final redirige HTTP a HTTPS y no
confía en cabeceras
`X-Forwarded-*` suministradas por el cliente. `TRUSTED_PROXIES` permanece vacío
mientras no se documente y restrinja una cadena de proxies concreta.

## Construcción y actualización

El bundle fuente se construye fuera de la microinstancia con dependencias y
assets ya resueltos. En Oracle se ejecuta:

```bash
sudo /srv/hubdigital/current/deploy/oracle/scripts/stage-linux-candidate.sh \
  /tmp/hubdigital-source.tar.gz

sudo env APPLY_MIGRATIONS=0 \
  /srv/hubdigital/staging/<id>/deploy/oracle/scripts/deploy-release.sh \
  /srv/hubdigital/staging/<artefacto>.tar.gz \
  /srv/hubdigital/staging/<artefacto>.tar.gz.sha256

sudo /srv/hubdigital/releases/<release-id>/deploy/oracle/scripts/activate-release.sh \
  <release-id>
sudo /srv/hubdigital/releases/<release-id>/deploy/oracle/scripts/activate-scheduler.sh \
  <release-id>
```

Usar `APPLY_MIGRATIONS=1` sólo tras revisar migraciones nuevas y contar con un
`pg_dump` reciente. La activación cambia `current` únicamente después de que
plataforma, PostgreSQL, R2, temporales y límites hayan pasado el preflight.

## Retencion automatica

Una activacion que termina correctamente ejecuta la politica de retencion. Se
conservan la release apuntada por `/srv/hubdigital/current` y la release
activada valida inmediatamente anterior. Se eliminan las demas releases, sus
estados, las releases fallidas y el contenido antiguo de
`/srv/hubdigital/staging`.

La politica no accede a PostgreSQL, R2, `/var/lib/hubdigital/backups` ni
`/etc/hubdigital/hubdigital.env`. Si la limpieza falla, la activacion permanece
valida y se muestra una advertencia para que el operador la revise.

Simulacion manual, sin borrar:

```bash
sudo /srv/hubdigital/current/deploy/oracle/scripts/cleanup-old-releases.sh --dry-run
```

Aplicacion manual:

```bash
sudo /srv/hubdigital/current/deploy/oracle/scripts/cleanup-old-releases.sh --apply
```

## Acceso inicial

El alta protegida se ejecutó exclusivamente para
`adrian.troya@epn.edu.ec`, con nombre `ADRIAN ESTEBAN TROYA PROANO` y rol de
administración del sistema. El correo de esa cuenta fue verificado de forma
individual por decisión administrativa documentada; la verificación global
permanece activa y el bootstrap quedó deshabilitado. No hay credenciales por
defecto ni contraseñas guardadas en este documento.

## Prueba funcional real

El expediente de validación `MEPN-INV-DEP-00001` comprobó login, alta de
depósito, OCR asíncrono, captura asistida, matriz Darwin Core y GBIF, carga R2,
descarga autenticada de los tres PDF y generación/descarga del PDF oficial. La
matriz quedó con 1/1 registros resueltos. Las tres descargas R2 devolvieron PDF
válido con HTTP 200 y hashes SHA-256 distintos.

La firma sintética comprobó integridad criptográfica, cobertura total,
coincidencia visual, vigencia y formato ETSI CAdES detached. El servidor la
rechazó correctamente porque su certificado autofirmado no pertenece a una
cadena confiable. El expediente no se envió: para concluir esa operación se
requiere el `.p12`/`.pfx` institucional de ADRIAN y su clave. No se desactivó
ni rebajó la verificación de certificados.

Las métricas de los flujos están en
`/var/lib/hubdigital/measurements/20260921T011825Z-real-flows-https-r2.*` y
`/var/lib/hubdigital/measurements/20260921T013644Z-matrix-signing-r2.*`. La
muestra limpia posterior al ajuste del tmpfs está en
`20260921T015222Z-postfix-https-r2.*`: 121 segundos, 381736 KiB de memoria
disponible mínima, 0 swap, 0 reinicios, 0 eventos OOM/cgroup y servicios
activos.

## Respaldo y recuperación

Respaldo final protegido:

`/var/lib/hubdigital/backups/20260920T204357Z-af678306a33ae2c0`

Incluye `pg_dump` en formato custom, entorno protegido, estado de release,
artefacto y checksum, configuración Nginx, reglas de firewall, DNS anterior y
`RECOVERY-MANIFEST.sha256`. Directorio 0700 y archivos 0600.

Procedimiento de recuperación:

1. Verificar integridad con `sha256sum -c RECOVERY-MANIFEST.sha256` dentro del
   directorio del respaldo.
2. Detener worker y scheduler, poner la aplicación en mantenimiento y tomar un
   respaldo del estado actual.
3. Restaurar PostgreSQL sólo en una base vacía con `pg_restore`; no superponer
   datos sin revisión.
4. Restaurar el entorno protegido con modo 0600 y el propietario esperado.
5. Volver a desplegar el artefacto cuyo checksum está en el respaldo y ejecutar
   los preflight antes de cambiar `current`.
6. Restaurar Nginx/firewall sólo si el incidente los afectó; validar con
   `nginx -t` antes de recargar.
7. Para revertir la publicación, el archivo `dns-before.json` conserva el
   registro previo. La reversión de DNS no reactiva automáticamente Tunnel.
8. Ejecutar la prueba completa de R2, sacar mantenimiento y activar primero el
   worker; activar el scheduler sólo después de revisar `schedule:list`.

## Preflight final

Con HTTPS ya instalado:

```bash
sudo /srv/hubdigital/current/deploy/oracle/scripts/preflight.sh
```

Además deben comprobarse `systemctl --failed`, los journals de Nginx, PHP-FPM,
worker, scheduler y PostgreSQL, y una prueba externa de
`https://dev.labinvepn.org`. La publicación no se considera completa hasta que
el origen sea accesible en OCI, el certificado sea válido y exista una cuenta
institucional usable.
