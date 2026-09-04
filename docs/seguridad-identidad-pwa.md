# Identidad, acceso y PWA

## Decisión de arquitectura

La aplicación usa Laravel Fortify para autenticación web, Sanctum para API y
autorización propia por roles persistidos. Keycloak no es necesario para esta
etapa: añadiría otro servicio, base de datos y operación sin resolver una
necesidad actual. Se evaluaría al integrar SSO institucional OIDC/SAML o varias
aplicaciones que deban compartir una identidad central.

## Ciclo de las cuentas

- Usuarios externos: alta en `/register`; solo pueden escoger Depositante o
  Solicitante y deben verificar su correo antes de usar el sistema.
- Usuarios internos: alta controlada con `php artisan usuarios:crear-interno`;
  admite Curador, Recepción y Administración, exclusivamente en dominios
  institucionales configurados.
- Usuarios anteriores: permanecen en `usuarios.users`; la migración de legado
  conserva su identidad, marca como verificados los existentes y crea su
  membresía en `usuarios.user_roles`.
- El correo se normaliza (espacios y mayúsculas) y `email_normalizado` tiene
  restricción única en PostgreSQL. Esto evita duplicados incluso ante dos
  solicitudes simultáneas.

Las credenciales incorrectas producen un mensaje genérico. La recuperación
también responde igual exista o no la cuenta, por lo que no revela usuarios.
Fortify limita intentos de inicio de sesión por correo canónico e IP.

## Contraseñas, sesiones y segundo factor

La contraseña se almacena mediante el cast `hashed` de Laravel y nunca se puede
descifrar. La aplicación compara hashes y los actualiza cuando cambia el costo
configurado. Las sesiones de base de datos se cifran; las cookies son `Secure`,
`HttpOnly` y `SameSite=Lax` en los ambientes HTTPS.

El usuario puede cambiar su clave en Configuración > Seguridad o recuperarla
desde el inicio de sesión. Ambas operaciones invalidan tokens y sesiones
anteriores. Activar o desactivar 2FA también revoca tokens y sesiones previas;
la solicitud web que efectuó el cambio recibe un identificador de sesión nuevo.
El 2FA TOTP y sus códigos de recuperación están habilitados por Fortify;
secretos y códigos se cifran con `APP_KEY` y se ocultan al serializar.

## Roles y API

La fuente de verdad es `usuarios.user_roles`; la columna `rol` conserva solo el
rol primario por compatibilidad. El servidor aplica middleware de rol, no confía
en valores del navegador. Los tokens API interactivos usan capacidades mínimas
por rol y nunca reciben `*` ni `esp32`. Los expedientes de depósito comprueban
además que pertenecen al usuario para prevenir acceso horizontal. Cada token
interactivo expira, por defecto, a las ocho horas (`AUTH_API_TOKEN_LIFETIME=480`).

Los correos de recuperación se encolan: el proceso web no espera al proveedor
SMTP y devuelve el mismo mensaje exista o no la cuenta. El ambiente debe
mantener `QUEUE_CONNECTION=database` y ejecutar un worker de cola.

## PWA y notificaciones push

La PWA incluye manifiesto, service worker y Push API real. Los PDF y expedientes
privados no se guardan en caché offline. Cada suscripción pertenece a un usuario
autenticado y verificado; solo se admiten proveedores push autorizados.

Para activar push en un ambiente HTTPS, después de instalar dependencias:

```bash
php artisan webpush:vapid
php artisan migrate --force
php artisan config:clear
php artisan queue:restart
```

`VAPID_PRIVATE_KEY` es un secreto de infraestructura: nunca debe guardarse en
Git, mostrarse al navegador ni copiarse entre aplicaciones. Las claves VAPID
deben mantenerse estables para no invalidar las suscripciones existentes.
