# Seguridad y privacidad del almacenamiento R2

## Alcance

El bucket `labinvepn-depositos-desarrollo` puede almacenar documentos regulatorios, datos personales, PDF firmados y certificados públicos contenidos en las firmas. Esos objetos siguen siendo confidenciales: esta autorización **no** habilita acceso público ni autoriza almacenar archivos `.p12`, claves privadas o contraseñas.

La aplicación es el único punto de acceso. Debe comprobar autenticación, rol y propiedad del expediente antes de entregar el contenido. No se habilitan `r2.dev`, dominios personalizados, listados públicos, CORS ni enlaces permanentes.

## Credencial de aplicación

La credencial de desarrollo es un **Account API token de Cloudflare compatible con S3**. Su ID se usa como `Access Key ID` y el SHA-256 de su valor secreto como `Secret Access Key`, según la especificación de R2. El token tiene solo `Workers R2 Storage Bucket Item Write`, que permite leer, listar, escribir y eliminar objetos, y su recurso es exclusivamente el bucket de desarrollo.

Los siguientes nombres se guardan como secretos de GitHub Codespaces y nunca en archivos versionados:

- `R2_ACCOUNT_ID`
- `R2_BUCKET`
- `R2_ACCESS_KEY_ID`
- `R2_SECRET_ACCESS_KEY`
- `R2_ENDPOINT`
- `DEPOSIT_STORAGE_DRIVER`

La credencial `labinvepn-r2-desarrollo` vence el 28 de febrero de 2027. Debe rotarse antes del vencimiento: crear una credencial nueva con el mismo alcance, actualizar secretos, reiniciar el Codespace, ejecutar la prueba integral y solo entonces revocar la anterior.

El token administrativo `hubdigital-bootstrap` se usa solamente para aprovisionamiento. Tiene permisos mucho mayores y no debe configurarse como credencial S3 de la aplicación. Al terminar el aprovisionamiento conviene reemplazarlo por tokens administrativos separados de alcance mínimo o revocarlo.

## Creación y comprobación

R2 debe activarse primero desde **Storage & databases > R2 > Overview**. La suscripción incluye el free tier mensual de la clase Standard; el consumo que lo exceda es facturable.

Desde PowerShell, con `CLOUDFLARE_API_TOKEN` cargado desde un gestor de secretos:

```powershell
./.devcontainer/configurar-r2-desarrollo.ps1
```

El comando es idempotente: crea el bucket Standard con sugerencia de ubicación `enam`, deshabilita `r2.dev`, elimina CORS, rechaza dominios personalizados y falla si encuentra reglas de borrado automático.

Después de reiniciar el Codespace para inyectar los secretos:

```bash
docker compose exec -T app php artisan depositos:verificar-almacenamiento --exigir-r2
```

La verificación crea un objeto sanitizado con nombre aleatorio, confirma `HEAD`, descarga y compara SHA-256, lo elimina y confirma su ausencia. No deja datos de prueba persistentes.

## Retención, auditoría y recuperación

- Desarrollo no configura vencimiento automático para expedientes. La tabla institucional de retención y el responsable de protección de datos deben definir los plazos antes de producción.
- Los objetos de conectividad bajo `pruebas-conectividad/` se eliminan inmediatamente por el mismo comando.
- Las altas, lecturas, reemplazos y eliminaciones funcionales deben conservar usuario, solicitud, objeto, SHA-256, fecha, resultado y motivo en la bitácora de la aplicación, sin registrar contenido ni credenciales.
- R2 no sustituye una copia de seguridad ni el plan de recuperación. Antes de producción se debe definir versionado lógico, restauración probada, conservación legal y eliminación autorizada.
- Las claves privadas de firma permanecen en el navegador durante la operación y nunca se almacenan en R2.

## Ubicación de datos

`enam` es solo una sugerencia de ubicación para reducir latencia desde Ecuador y no constituye por sí misma una garantía contractual de residencia. La jurisdicción definitiva y cualquier transferencia internacional de datos deben aprobarse institucionalmente antes de producción.
