# Desarrollo gratuito sin instalar PHP en Windows

Este repositorio puede probarse con GitHub Codespaces y GitHub Actions. No
requiere PHP, PostgreSQL, Node.js ni Docker instalados en la computadora local.

## Crear el Codespace

1. Abrir el repositorio en GitHub y seleccionar **Code > Codespaces**.
2. Elegir **Create codespace on la rama de trabajo vigente**.
3. Usar la máquina de 2 núcleos. La configuración del repositorio construye la
   aplicación, inicia PostgreSQL, Nginx, las colas, el scheduler y Mailpit, y
   ejecuta las migraciones.
4. En la pestaña **Ports**, abrir el puerto 80 para HubDigital y el 8025 para
   consultar los correos de prueba.

La primera construcción puede tardar varios minutos. En los siguientes inicios
se reutilizan las imágenes y volúmenes del Codespace.

## Habilitar `dev.labinvepn.org`

El dominio de desarrollo sólo funciona mientras el Codespace está encendido.
En GitHub, ir a **Settings > Secrets and variables > Codespaces**, crear el
secreto `CLOUDFLARE_TUNNEL_TOKEN` y limitarlo a este repositorio. Al reiniciar el
Codespace, el contenedor `cloudflared` conectará el entorno con Cloudflare.

No guardar el valor del token en `.env`, commits, capturas o mensajes.

## Comandos de operación

```bash
# Arrancar o reconstruir todo
bash .devcontainer/start.sh

# Ver el estado
docker compose --profile development --profile tunnel ps

# Ver registros de Laravel
docker compose logs --tail=100 app worker

# Detener los contenedores sin borrar datos
docker compose --profile development --profile tunnel stop
```

Para evitar consumir la cuota gratuita, detener el Codespace desde GitHub al
terminar la sesión. No ejecutar `docker compose down -v`: la opción `-v`
elimina la base de datos del entorno.

## Validación automática

Cada pull request hacia `main` o `develop` ejecuta gratuitamente en GitHub
Actions:

- formato PHP con Laravel Pint;
- compilación de Vite y Tailwind;
- pruebas PHPUnit/Pest con SQLite;
- escenarios Behat marcados con `@listo` en PHP 8.4 y 8.5.

## Prueba integral con usuarios desechables

En Codespaces, define `SEED_DEMO_USERS=true` y opcionalmente una contraseña en `DEMO_DEPOSITOS_PASSWORD`. Después ejecuta `php artisan db:seed`. El seeder crea un depositante, un receptor EPN y un curador, muestra la contraseña temporal en la consola y se niega a ejecutarse en producción.

## Probar la alerta de acta pendiente

1. Ingresar con la cuenta de recepción y marcar un lote como recibido y constatado.
2. HubDigital crea una notificación de base de datos y envía el correo al curador.
3. Ingresar con `test.curaduria@labinvepn.test`. La campana muestra la alerta y el botón abre directamente el acta del depósito correspondiente.
4. La misma tarea aparece en **Bandeja de recepciones > Actas pendientes**, aunque la notificación ya se haya marcado como leída.
5. Para avisos del sistema operativo mientras el portal está abierto, pulsar **Activar avisos en este dispositivo**. Esta función requiere HTTPS y permiso explícito del navegador.

El service worker no intercepta solicitudes ni guarda expedientes o PDF en caché. La entrega en segundo plano con el navegador totalmente cerrado requerirá configurar posteriormente un proveedor Web Push/VAPID; durante el desarrollo gratuito la alerta garantizada es la combinación de campana, cola de actas y correo en Mailpit.

## Confianza de firma electrónica

En desarrollo, `FIRMA_EXIGIR_CERTIFICADO_CONFIABLE=false` permite ensayar certificados sin haber preparado todavía el almacén institucional. La firma, el `ByteRange` y la inalterabilidad del PDF sí se validan.

El Firmador HubDigital procesa el `.p12/.pfx` y su contraseña dentro de un Web Worker efímero del navegador; el servidor recibe solamente el PDF firmado. Para las solicitudes y actas generadas por HubDigital se exige una única firma final `ETSI.CAdES.detached`, cobertura completa, contenido visual idéntico al PDF oficial y certificado vigente. El expediente registra la huella SHA-256 del PDF, el usuario autenticado que ejecutó la firma y los datos del certificado informados por `pdfsig`.

Los certificados reales y archivos de credenciales deben permanecer fuera del repositorio. `.gitignore` bloquea `.p12`, `.pfx` y archivos de credenciales relacionados. La prueba privada local debe ejecutarse en memoria y no conservar el PDF de ensayo.

Antes de producción se debe crear una base NSS con las raíces e intermedias vigentes de las entidades acreditadas por ARCOTEL, montarla en los contenedores y configurar `FIRMA_NSS_DIR=sql:/ruta/a/nssdb`. En producción `FIRMA_EXIGIR_CERTIFICADO_CONFIABLE=true` provoca un cierre seguro: HubDigital rechaza la firma si `pdfsig` no puede establecer la cadena de confianza. No debe utilizarse la opción `-no-ocsp`; el validador queda preparado para que Poppler consulte OCSP y las CRL disponibles.
