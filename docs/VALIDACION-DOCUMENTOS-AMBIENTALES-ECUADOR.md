# Validación de documentos ambientales de Ecuador

Fecha de revisión: **2026-09-04**  
Versión de reglas: **`ec-autoridad-ambiental-2026.09.1`**

## Decisión de diseño

Los documentos regulatorios se procesan localmente con Poppler y, cuando el PDF no
contiene texto suficiente, con el modelo OCR preentrenado y gratuito Tesseract 5
(`spa+eng`). No se envían permisos, identificaciones ni firmas a servicios de IA de
terceros.

La clasificación no usa el nombre del archivo ni coordenadas fijas de una plantilla.
Combina señales semánticas de cuatro clases: naturaleza del acto, autoridad, operación
autorizada y estructura probatoria. Cada valor propuesto conserva fragmento, regla,
versión y confianza. El sistema nunca marca automáticamente un documento como
definitivamente válido: los únicos resultados automáticos son `RECHAZADO` y
`REVISION_HUMANA`.

Una modificación de membrete, tipografía, paginación o disposición de tablas no requiere
cambiar la lógica. Una nueva familia semántica o de códigos se incorpora como regla
versionada y fixture sanitizado.

## Base oficial consultada

- [Procedimiento INABIO para autorizaciones de recolección, contratos y sus actos](https://www.biodiversidad.gob.ec/wp-content/uploads/2024/07/Procedimiento_Contrato_Marco_y_Permisos_.pdf), versión II validada el 1 de mayo de 2024. Define la autorización de recolección y la guía de movilización, y establece que la guía se genera en SUIA sobre un proceso de recolección o contrato previamente aprobado.
- [Catálogo oficial de formularios y formatos MAATE (LOTAIP)](https://www.ambiente.gob.ec/wp-content/uploads/Literal-f1-Formularios-o-formatos-de-solicitudes-agosto-2023.pdf), agosto de 2023. Describe la aprobación de movilización como el instrumento para transportar especímenes y muestras biológicas.
- [Acuerdo MAATE-MAATE-2025-0003-A](https://www.ambiente.gob.ec/wp-content/uploads/downloads/2025/03/ACUERDO-Nro.-MAATE-MAATE-2025-0003-A.pdf), 2025. Confirma que recolección, movilización, intercambio y donación de vida silvestre requieren autorización de la autoridad ambiental nacional.
- [Ejemplos oficiales publicados por ARCONEL](https://arconel.gob.ec/consulta-ambiental-permisos-recolec-vida-silvestre/), consulta del 4 de septiembre de 2026. Los documentos públicos 2025 evidencian variaciones entre direcciones zonales en código, tablas, duración y denominación de campos.
- [Estudio oficial MAATE con referencias a permisos y guías](https://maatecalidadambiental.ambiente.gob.ec/wp-content/uploads/2024/07/7.2-Linea-Base-Biotica_V6_2.pdf), julio de 2024. Evidencia familias de códigos con `GM-VS`, oficinas técnicas, direcciones zonales y siglas MAATE.

Estas fuentes determinan las relaciones de negocio; no se usan como una lista cerrada de
plantillas visuales.

## Corpus privado evaluado

Los ocho PDF reales suministrados se inspeccionaron localmente. **No forman parte del
repositorio ni de los fixtures**. Los tests contienen textos completamente sintéticos.

| Documento local | Familia semántica observada | Cobertura sanitizada equivalente |
|---|---|---|
| GUIA DE MOVILIZACION RASPAS.pdf | Guía zonal 2026, ruta y tabla de muestras | `guia_zonal_2026_ocr_mutado.txt` |
| GuiaMovilizacion Tiputini.pdf | Guía/autorización SUIA, número simple y código separado | `guia_suia_2023.txt` |
| Guía de movilización.pdf | Guía zonal 2026, tabla multipágina | `guia_zonal_2026_ocr_mutado.txt` |
| MAE-DZ8-2026-1691-OF.pdf | Oficio Quipux que otorga una autorización | `autorizacion_oficio_2026.txt` |
| MEPN-Ent-Recepción-2023-006_AIC.pdf | Autorización zonal tabular 2022 | `autorizacion_zonal_2025.txt` |
| MEPN-Ent-Recepción-2023-006_GM.pdf | Guía zonal 2022 y códigos de campo cortos | `guia_zonal_2022.txt` |
| permiso de investigación.pdf | Autorización zonal tabular 2026 | `autorizacion_zonal_2025.txt` |
| recoleccion_mepninv485.pdf | Autorización SUIA con número y código separados | `autorizacion_suia_2023.txt` |

## Reglas conservadoras

1. Una mención casual a “permiso” o “guía” no clasifica el archivo. Se exige un título o
   acto equivalente y señales operativas independientes.
2. Los acrónimos `MAE`, `MAAE`, `MAATE` y las denominaciones ministeriales históricas
   se aceptan como indicio, nunca como única prueba.
3. Los códigos se extraen únicamente junto a etiquetas semánticas. Dos candidatos
   incompatibles para el mismo campo producen rechazo y no autocompletado.
4. Fechas imposibles se descartan. Se admiten fechas ISO, numéricas y en español, y se
   contrastan emisión, vigencia de autorización y período de movilización.
5. La guía debe referenciar la autorización aportada. También se contrastan RUC cuando
   ambos documentos lo contienen, organización, proyecto y vigencias.
6. Los códigos de campo solo se leen dentro de una sección de muestras reconocida. Una
   placa u otro código fuera de esa sección no se convierte en número de muestra.
7. Un campo se propone solo si tiene evidencia propia y confianza mínima de 0,78. La
   persona usuaria debe confirmarlo; una contradicción del expediente bloquea todas las
   propuestas procedentes del clasificador ambiental.
8. La leyenda “firmado electrónicamente” no demuestra integridad ni autoría. La
   verificación criptográfica continúa siendo una etapa separada.

## Evolución segura

Ante un nuevo formato se conserva el PDF fuera del repositorio público, se elimina toda
información personal para producir un fixture, se añade una regla con identificador y
versión, y se ejecutan pruebas de documento correcto, mutación de formato, ambigüedad,
contradicción y documento incorrecto. No se reduce el umbral global para hacer pasar un
caso aislado.
