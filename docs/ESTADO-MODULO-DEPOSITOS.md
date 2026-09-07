# Estado del módulo de depósitos MEPN

Fecha de corte: 5 de septiembre de 2026.

## Correcciones posteriores a la aceptación funcional

La aceptación funcional de Luna sobre la versión `ff385887` reprodujo dos
defectos de presentación que bloqueaban o degradaban el recorrido:

- El menú de usuario del layout lateral pasaba `variant="sidebar"` a un
  componente propio. Esa propiedad colisionaba con la variante interna de los
  iconos Flux y terminaba siendo evaluada como una variante de icono no válida
  (`sidebar`), provocando `UnhandledMatchError` en el área autenticada. El
  contexto visual del menú ahora se expresa mediante la propiedad propia
  `context`, separada de los atributos de Flux.
- El botón del chat público usaba `:aria-label` con una variable Livewire. El
  prefijo `:` hacía que Alpine intentara evaluar `$abierto` fuera de un
  `x-data`. La etiqueta, `aria-expanded`, el panel controlado y el estado de
  apertura se derivan ahora del mismo estado Livewire; durante el cambio el
  botón queda deshabilitado para evitar solicitudes duplicadas.

Estas correcciones describen código preparado para desplegar. No acreditan por
sí solas la aceptación funcional del trámite, extracción, R2, notificaciones,
firma electrónica, recepción, acta o ingreso a colección. Esos recorridos
deben repetirse en desarrollo después del despliegue.

## Estado de disponibilidad

- **Codificado:** portal público, trámite guiado, extracción local, catálogos
  taxonómicos controlados, recepción, curaduría, actas, firmador, gestión web
  de usuarios, paneles analíticos y alertas Web Push.
- **Configurado mediante secretos:** R2 y VAPID se reciben desde secretos de
  Codespaces; sus valores no viven en este repositorio.
- **Desplegado:** este documento no declara despliegues remotos.
- **No comprobado en ejecución:** esta descripción registra código y
  configuración, no sustituye una validación de ambiente.

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
- Alerta al curador por base de datos, correo y Web Push con VAPID. La alerta
  abre el expediente exacto y también permanece en la bandeja de actas
  pendientes.
- Acta final generada únicamente después de la constatación física. Los
  especímenes ingresan a la colección solo después de validar y guardar el acta
  firmada por curaduría.
- Gestión web de cuentas, roles, recuperación de contraseña, verificación de
  correo y 2FA mediante Laravel Fortify; las altas reales se realizan desde las
  pantallas institucionales de administración.

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
8. Conservación privada del PDF oficial preparado para el acta; la descarga y
   comparación de firma se basan en esa misma versión, no en una regeneración.

El certificado y la contraseña no forman parte del repositorio. La validez de
una firma y su aceptación institucional deben confirmarse en el ambiente que
corresponda.

## Arquitectura de desarrollo gratuito

```text
Navegador/PWA
  -> dev.labinvepn.org (Cloudflare Tunnel, sin Cloudflare Access)
  -> Nginx
  -> Laravel 13 + Livewire 4
       -> PostgreSQL
       -> worker de colas: OCR, extracción, alertas y correo
       -> almacenamiento privado R2 (configurado mediante secretos)
       -> Mailpit para inspeccionar correo de pruebas
```

Todo se ejecuta en GitHub Codespaces con Docker Compose. El dominio sigue
registrado en GoDaddy, mientras Cloudflare administra DNS, TLS y el túnel. No se
usa Hetzner en esta etapa y no hace falta instalar PHP, Composer, PostgreSQL o
Docker en Windows.

## Operación en Codespaces

```bash
bash .devcontainer/start.sh
docker compose -p hubdigital-dev exec -T app php artisan migrate --force
# La imagen Docker construye Vite en su etapa frontend; app no contiene npm.
docker compose -p hubdigital-dev --profile development up -d --build app worker scheduler nginx
```

El diagnóstico de lectura/escritura R2 es explícito y no forma parte del
arranque: `bash .devcontainer/start.sh --verificar-r2`.

## Correcciones posteriores a la primera aceptación

- La asesoría por ausencia de documentos y la revisión de documentos cargados
  son recorridos distintos. Una incertidumbre documental conserva el expediente,
  sus evidencias y su huella, y pasa a la cola curatorial sin declarar falsamente
  que el depositante no presentó documentos.
- La campana entrega avisos pendientes por cursores de sesión, en lotes ordenados,
  sin marcarlos como leídos. El navegador conserva su identidad por notificación
  para no repetir avisos durante una navegación Livewire.
- Cada original de acta se materializa con una referencia y ruta inmutables,
  versión, SHA-256 y bitácora de reemplazos. Firma, visualización y descarga usan
  la misma versión; una firma se rechaza si el original cambió durante el proceso.
- La reemisión es explícita y queda reservada para un original no verificable.
  Nunca se regenera de forma silenciosa al descargar o firmar.

## Preparación de aceptación funcional

Luna debe obtener las cuentas ficticias mediante la administración web del
ambiente de desarrollo: una cuenta de consultor, una de receptor y una de
curador. El correo de desarrollo se consulta en Mailpit. Los documentos de
ensayo deben estar identificados como ficticios, conservar la estructura mínima
del tipo documental que representan y no deben publicarse como permisos reales.
El material de firma se entrega por el mecanismo privado institucional; no se
versionan certificados ni claves. `adrian.troya@epn.edu.ec` se conserva como
curador administrador y no se gestiona mediante seeders o SQL.

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

- Ejecutar la aceptación funcional institucional del recorrido anterior en el
  ambiente de desarrollo. Esta documentación no sustituye dicha aceptación.
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
- Confirmar en el ambiente correspondiente la entrega de Web Push con VAPID
  para navegadores cerrados o sin conexión; el código está implementado pero
  este documento no declara una comprobación de ejecución.
- Definir la infraestructura institucional futura, la estrategia de copias de
  seguridad/restauración, observabilidad y rotación de secretos. Esta etapa no
  usa Hetzner.
- Realizar revisión jurídica de protección de datos, conservación documental,
  firma electrónica y términos de depósito con la EPN.
