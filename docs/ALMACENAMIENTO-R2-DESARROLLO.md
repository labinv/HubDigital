# Almacenamiento privado R2 para depositos

Los documentos regulatorios, solicitudes firmadas y actas finales se guardan como objetos privados. La aplicacion los entrega solamente despues de autorizar al depositante, receptor o curador; el bucket no necesita acceso publico.

## Credenciales de desarrollo

Crear un bucket separado, por ejemplo `hubdigital-depositos-dev`, y una credencial S3 de R2 con **Object Read & Write** limitada exclusivamente a ese bucket. El token general de la API de Cloudflare no sustituye el `Access Key ID` y `Secret Access Key` de R2.

En GitHub Codespaces, registrar estos secretos sin escribirlos en el repositorio:

- `R2_ACCOUNT_ID`
- `R2_BUCKET`
- `R2_ACCESS_KEY_ID`
- `R2_SECRET_ACCESS_KEY`
- `DEPOSIT_STORAGE_DRIVER=r2`

`R2_ENDPOINT` es opcional; por defecto se construye como `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`. La region de firma siempre es `auto`, como exige R2.

Documentacion oficial: [API S3 de R2](https://developers.cloudflare.com/r2/get-started/s3/) y [tokens de R2](https://developers.cloudflare.com/r2/api/tokens/).

## Comprobacion reproducible

Desde Codespaces, una vez levantados los contenedores:

```bash
bash .devcontainer/prueba-integral-depositos.sh
```

La primera prueba escribe un objeto aleatorio, lo vuelve a leer, compara SHA-256 y lo elimina. `--exigir-r2` impide que un resultado local sea confundido con una prueba remota.

Sin credenciales, `auto` usa almacenamiento local solo en `APP_ENV=local` o `testing`. Una configuracion parcial siempre falla; producción exige almacenamiento remoto por defecto.
