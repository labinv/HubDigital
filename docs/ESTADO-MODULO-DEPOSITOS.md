# Estado del módulo de depósitos MEPN

Fecha de corte: 4 de septiembre de 2026.

## Alcance implementado

- Portal informativo público en `/depositos`; la autenticación se exige al abrir
  el trámite y no para consultar la portada.
- Registro guiado para consultores y depositantes, con datos del trámite,
  procedencia, matriz de material, documentos regulatorios, revisión y envío.
- Lectura local de PDF con Poppler y OCR Tesseract en español/inglés. La
  clasificación combina señales de contenido, códigos, institución, proyecto y
  fechas; el nombre del archivo no determina su tipo.
- Carga separada y cotejo cruzado de autorización ambiental y guía de
  movilización. Los resultados, advertencias, método de extracción y evidencia
  quedan auditados en PostgreSQL.
- Datos de la matriz `Datos depósito material MEPN.xlsx` proyectados a campos
  estructurados. Los nombres científicos usan selecciones controladas y
  referencias Darwin Core/GBIF en lugar de texto libre cuando es posible.
- Solicitud oficial generada como PDF por HubDigital y firmada por el
  depositante con el Firmador HubDigital.
- Recepción física separada de curaduría: el receptor EPN escanea/resuelve el QR,
  verifica el lote y deja constancia de recepción o de observaciones.
- Alerta al curador por base de datos, correo y notificación del sistema
  operativo mientras la PWA está abierta. La alerta abre el expediente exacto y
  también permanece en la bandeja de actas pendientes.
- Acta final generada únicamente después de la constatación física. Los
  especímenes ingresan a la colección solo después de validar y guardar el acta
  firmada por curaduría.
- Cuentas desechables de depositante, receptor y curador para el recorrido de
  extremo a extremo.

## Firma electrónica propia

El navegador carga el `.p12/.pfx` y su contraseña dentro de un Web Worker
efímero. Ninguno de esos secretos se envía al servidor ni se guarda en el
navegador. El servidor recibe únicamente el PDF firmado y aplica estas
comprobaciones antes de cerrar el expediente:

1. PDF estructuralmente válido y dentro del límite de páginas configurado.
2. Una sola firma final `ETSI.CAdES.detached` para documentos generados por el
   sistema.
3. `ByteRange` válido y cobertura completa de la última revisión del PDF.
4. Firma criptográfica válida y certificado vigente.
5. Igual número/tamaño de páginas y contenido textual y visual equivalente al
   PDF oficial sin firmar.
6. Registro del usuario firmante, propósito, certificado, fecha y SHA-256 del
   PDF.
7. Bloqueo transaccional del expediente para impedir que dos firmas
   concurrentes se sobrescriban.

El certificado real entregado para desarrollo fue probado exclusivamente en
memoria: identidad, vigencia, correspondencia entre clave privada y certificado
y creación de CAdES resultaron correctas. El certificado y la contraseña no
forman parte del repositorio.

## Arquitectura de desarrollo gratuito

```text
Navegador/PWA
  -> dev.labinvepn.org (Cloudflare Tunnel, sin Cloudflare Access)
  -> Nginx
  -> Laravel 13 + Livewire 4
       -> PostgreSQL
       -> worker de colas: OCR, extracción, alertas y correo
       -> almacenamiento privado de documentos
       -> Mailpit para inspeccionar correo de pruebas
```

Todo se ejecuta en GitHub Codespaces con Docker Compose. El dominio sigue
registrado en GoDaddy, mientras Cloudflare administra DNS, TLS y el túnel. No se
usa Hetzner en esta etapa y no hace falta instalar PHP, Composer, PostgreSQL o
Docker en Windows.

## Verificación en Codespaces

```bash
bash .devcontainer/start.sh
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed
docker compose exec -T app php artisan test tests/Unit/AnalizadorDocumentoAmbientalTest.php
docker compose exec -T app php artisan test tests/Unit/DetalleValidacionFirmaTest.php
docker compose exec -T app php artisan test tests/Feature/FlujoDepositoE2ETest.php
docker compose exec -T app vendor/bin/behat --profile=recepcion \
  --suite=RecepcionMuestrasFisicas
docker compose exec -T app npm run test:signer
docker compose exec -T app npm run build
```

Para probar con una contraseña conocida, definir solo en el entorno de
desarrollo:

```dotenv
SEED_DEMO_USERS=true
DEMO_DEPOSITOS_PASSWORD=una-clave-temporal-larga
```

Las cuentas creadas son:

- `test.depositante@labinvepn.test`
- `test.recepcion@labinvepn.test`
- `test.curaduria@labinvepn.test`

## Recorrido de aceptación

1. Abrir `/depositos` sin iniciar sesión y comprobar que toda la información
   pública sea visible.
2. Autenticarse como depositante, crear el trámite, seleccionar taxonomía,
   cargar la matriz y los dos documentos regulatorios.
3. Esperar el procesamiento de cola, revisar las evidencias extraídas y corregir
   o confirmar los datos sugeridos.
4. Generar la solicitud, firmarla con un certificado de ensayo y enviarla.
5. Realizar la revisión documental con el rol correspondiente al flujo
   existente.
6. Ingresar como receptor, abrir el lote mediante el QR y marcarlo recibido y
   constatado.
7. Comprobar el correo en Mailpit y la alerta de curaduría.
8. Ingresar como curador desde el enlace de la alerta, generar el acta final y
   firmarla.
9. Verificar que el depositante pueda descargar el acta y que el lote figure
   ingresado a la colección.
10. Intentar una segunda firma del acta y comprobar que el sistema responda con
    conflicto sin reemplazar el primer archivo.

## Pendientes antes de producción

- Ejecutar el recorrido completo anterior dentro de Codespaces, porque el
  equipo Windows actual no tiene PHP, Composer, Docker ni `pdfsig`.
- Instalar en la base NSS del servidor las raíces e intermedias vigentes de las
  entidades certificadoras acreditadas por ARCOTEL y activar
  `FIRMA_EXIGIR_CERTIFICADO_CONFIABLE=true`.
- Validar OCSP/CRL, renovación, revocación y sellado de tiempo con certificados
  institucionales autorizados; la firma CAdES implementada no equivale por sí
  sola a una evaluación legal u homologación oficial.
- Configurar correo transaccional institucional. Mailpit es únicamente para
  desarrollo.
- Incorporar antivirus/antimalware para archivos subidos antes de abrir el
  portal en producción.
- Implementar Web Push con VAPID si se requieren avisos con el navegador
  totalmente cerrado. La bandeja, correo y notificación con la PWA abierta ya
  están disponibles.
- Desplegar en Hetzner, montar almacenamiento persistente/cifrado, automatizar
  copias de seguridad y restauración, observabilidad y rotación de secretos.
- Realizar revisión jurídica de protección de datos, conservación documental,
  firma electrónica y términos de depósito con la EPN.

