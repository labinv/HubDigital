# Revisión documental y acta de recepción

La revisión de incertidumbres de documentos usa el estado **Pendiente de
Revisión Documental Previa**. Su resolución favorable devuelve el expediente a
borrador editable: no aprueba el trámite ni genera un QR. El consultor debe
completar, generar, firmar y enviar la solicitud antes de la revisión final por
curaduría.

Las solicitudes y resoluciones documentales se agregan a una bitácora con
responsable, fecha, motivo, versión y huella de los documentos persistidos. Una
extracción tardía puede aportar datos, pero no reemplaza una decisión humana
vigente.

La campana confirma desde el navegador el aviso interno antes de retirarlo de
la cola; confirmar entrega no equivale a marcarlo como leído. La recepción que
requiere acta usa una identidad de evento compartida por la campana y Web Push,
y conserva la ruta concreta del expediente.

Cada original de acta es versionado. El firmador recibe la referencia y SHA-256
de la versión que abrió y los envía junto con el PDF firmado. El servidor los
contrasta antes de validar y nuevamente durante el cierre transaccional; una
firma sobre una versión sustituida se rechaza. La reemisión exige la versión que
se pretende sustituir y preserva los objetos anteriores.

El certificado `.p12/.pfx` y su clave se procesan localmente en el navegador.
No se guardan en el repositorio ni se envían al servidor.
