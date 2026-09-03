# Desarrollo gratuito sin instalar PHP en Windows

Este repositorio puede probarse con GitHub Codespaces y GitHub Actions. No
requiere PHP, PostgreSQL, Node.js ni Docker instalados en la computadora local.

## Crear el Codespace

1. Abrir el repositorio en GitHub y seleccionar **Code > Codespaces**.
2. Elegir **Create codespace on feature/depositos-produccion**.
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
