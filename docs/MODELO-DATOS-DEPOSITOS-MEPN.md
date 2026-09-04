# Modelo de datos del depósito MEPN

La fuente funcional para el formulario del consultor es **Datos depósito material MEPN.xlsx**. HubDigital reproduce el formulario dentro de la aplicación; el usuario no tiene que descargar, editar ni volver a subir esa hoja.

## Responsabilidad de los campos

| Columna oficial | Responsable | Fuente en HubDigital |
|---|---|---|
| A. Nombre representante legal empresa | Consultor | Perfil autenticado y contraste con los documentos |
| B. Cargo o posición | Consultor | Perfil autenticado |
| C. Empresa o Institución | Consultor | Perfil autenticado |
| D. No. permiso recolección | Consultor | Extraído de la autorización y confirmado |
| E. No. permiso movilización | Consultor | Extraído de la guía y confirmado |
| F. Grupo animal | Consultor | Lista maestra de invertebrados y documentos |
| G. No. individuos | Consultor | Documento cuando existe un total inequívoco; en otro caso, confirmación manual |
| H. No. de morfoespecies | Consultor | Confirmación del consultor |
| I. No. de lotes | Consultor | Suma de cantidades expresadas como lotes y confirmación |
| J. Localidad | Consultor | Una localidad específica y su provincia entre paréntesis, según la guía y la confirmación del consultor |
| K. No. de proceso | Receptor EPN | Código del lote/expediente |
| L. Fecha de recepción de los especímenes | Receptor EPN | Marca de constatación física |
| M. Período | Receptor EPN | Año de recepción |
| N. Observaciones | Receptor EPN / curaduría | Cadena de custodia |
| O. Estado | Sistema / curaduría | Estado vigente del lote |

Los renglones históricos de la hoja original contienen información personal y no se usan como datos semilla.

## Plantilla interna

**Plantilla base datos_laboratorio invertebrados_v2.xlsx** es el modelo técnico interno de 106 campos. El detalle ingresado mediante listas EPN/GBIF se normaliza a esa estructura y a términos Darwin Core. No es el formulario que debe llenar el consultor.

El material registrado por el consultor permanece con disposición `pending accession`. HubDigital solo lo incorpora definitivamente a la colección después de la constatación física, la generación del acta final y su firma electrónica válida por curaduría.

## Validación documental

El nombre del PDF no interviene en la clasificación. El sistema analiza contenido, estructura, entidad emisora, códigos, fechas, titular, organización, proyecto, muestras y declaración de firma. Después contrasta autorización y guía como un solo expediente. Si ambos archivos son auténticos pero citan autorizaciones, organizaciones o proyectos diferentes, se rechaza la combinación.

La firma electrónica se comprueba criptográficamente con `pdfsig`: no basta la leyenda visible del documento. Cuando hay varias firmas incrementales, todas deben ser válidas y la última debe cubrir la revisión completa del PDF. El estado y la fecha de esta verificación quedan registrados por cada autorización y guía. Una copia escaneada sin firma digital se marca para revisión curatorial; una firma presente pero inválida o imposible de verificar bloquea el avance.
