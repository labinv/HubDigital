/**
 * Firmador de PDF de HubDigital. El P12 y su contrasena se entregan a un Web
 * Worker efimero y nunca forman parte de una peticion HTTP.
 */
window.hubDigitalFirmador = (config) => ({
    estado: 'listo',
    progreso: '',
    error: '',

    async firmar() {
        this.error = '';

        const archivo = this.$refs.certificado?.files?.[0];
        const clave = this.$refs.clave?.value ?? '';
        if (!archivo || !/\.(p12|pfx)$/i.test(archivo.name)) {
            this.error = 'Selecciona un certificado .p12 o .pfx.';
            return;
        }
        if (clave.length === 0) {
            this.error = 'Ingresa la contraseña del certificado.';
            return;
        }

        this.estado = 'procesando';
        this.progreso = 'Obteniendo el PDF oficial…';

        let trabajador;
        let certificadoBytes;
        try {
            const respuestaPdf = await fetch(config.documentUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/pdf' },
            });
            if (!respuestaPdf.ok) {
                throw new Error('No se pudo obtener el PDF oficial para firmar.');
            }

            const pdfBytes = await respuestaPdf.arrayBuffer();
            certificadoBytes = await archivo.arrayBuffer();
            this.progreso = 'Leyendo el certificado y creando la firma local…';

            trabajador = new Worker(
                new URL('./workers/pdf-signing.worker.js', import.meta.url),
                { type: 'module', name: 'hubdigital-firmador' },
            );

            const resultado = await new Promise((resolve, reject) => {
                const temporizador = window.setTimeout(
                    () => reject(new Error('La operación de firma excedió el tiempo permitido.')),
                    90000,
                );

                trabajador.onmessage = (evento) => {
                    window.clearTimeout(temporizador);
                    evento.data?.ok ? resolve(evento.data) : reject(new Error(evento.data?.error));
                };
                trabajador.onerror = () => {
                    window.clearTimeout(temporizador);
                    reject(new Error('El firmador local no pudo iniciarse.'));
                };
                trabajador.postMessage({
                    pdf: pdfBytes,
                    p12: certificadoBytes,
                    passphrase: clave,
                    reason: config.reason,
                    location: config.location ?? 'Quito, Ecuador',
                }, [pdfBytes, certificadoBytes]);
            });

            this.progreso = 'Validando la firma y la integridad del documento…';

            const formulario = new FormData();
            formulario.append('pdf_firmado', new Blob([resultado.pdf], { type: 'application/pdf' }), 'documento-firmado.pdf');

            const respuesta = await fetch(config.uploadUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: formulario,
            });
            const cuerpo = await respuesta.json().catch(() => ({}));
            if (!respuesta.ok) {
                throw new Error(cuerpo.message ?? 'El servidor rechazó el documento firmado.');
            }

            this.estado = 'completado';
            this.progreso = cuerpo.message ?? 'Documento firmado y validado.';
            this.$refs.clave.value = '';
            this.$refs.certificado.value = '';
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: this.progreso } }));
            window.setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            this.estado = 'error';
            this.progreso = '';
            this.error = this.mensajeSeguro(error);
        } finally {
            trabajador?.terminate();
            if (certificadoBytes && certificadoBytes.byteLength > 0) {
                new Uint8Array(certificadoBytes).fill(0);
            }
            if (this.$refs.clave) {
                this.$refs.clave.value = '';
            }
        }
    },

    mensajeSeguro(error) {
        const mensaje = String(error?.message ?? 'No se pudo firmar el documento.');
        if (/password|passphrase|PKCS#12|Invalid password|MAC could not be verified/i.test(mensaje)) {
            return 'No se pudo abrir el certificado. Verifica el archivo y su contraseña.';
        }
        return mensaje;
    },
});
