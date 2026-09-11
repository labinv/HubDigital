import assert from 'node:assert/strict';

globalThis.window = {
    clearTimeout,
    dispatchEvent() {},
    location: { reload() {} },
    setTimeout,
};
globalThis.CustomEvent = class CustomEvent {
    constructor(type, options = {}) {
        this.type = type;
        this.detail = options.detail;
    }
};
globalThis.document = {
    querySelector() { return { content: 'csrf-sintetico' }; },
};

await import('../../resources/js/hubdigital-firmador.js');

const config = {
    documentUrl: '/documento.pdf',
    uploadUrl: '/firma',
    signatureProfile: 'solicitud-deposito:depositante:v1',
    reason: 'Prueba de seguridad',
};

function componente(certificado, clave = 'secreto-sintetico') {
    return Object.assign(window.hubDigitalFirmador(config), {
        $refs: {
            certificado: { files: certificado ? [certificado] : [], value: certificado?.name ?? '' },
            clave: { value: clave },
        },
    });
}

const invalido = componente({ name: 'certificado.txt' });
await invalido.firmar();
assert.equal(invalido.$refs.clave.value, '');
assert.equal(invalido.$refs.certificado.value, '');

let copiaP12;
const certificado = {
    name: 'certificado-sintetico.p12',
    async arrayBuffer() {
        copiaP12 = Uint8Array.from([1, 2, 3, 4]).buffer;

        return copiaP12;
    },
};
globalThis.fetch = async () => ({
    ok: true,
    async arrayBuffer() { return Uint8Array.from([37, 80, 68, 70]).buffer; },
});
globalThis.Worker = class WorkerFallido {
    postMessage() {
        queueMicrotask(() => this.onmessage({ data: { ok: false, error: 'Invalid password' } }));
    }

    terminate() {}
};

const fallido = componente(certificado);
await fallido.firmar();
assert.equal(fallido.$refs.clave.value, '');
assert.equal(fallido.$refs.certificado.value, '');
assert.ok(new Uint8Array(copiaP12).every((byte) => byte === 0));
assert.match(fallido.error, /Verifica el archivo y su contraseña/u);

let camposSubidos = [];
globalThis.Worker = class WorkerExitoso {
    postMessage() {
        const pdf = Uint8Array.from([37, 80, 68, 70, 45, 49, 46, 55]).buffer;
        queueMicrotask(() => this.onmessage({ data: { ok: true, pdf } }));
    }

    terminate() {}
};
globalThis.fetch = async (url, options = {}) => {
    if (url === config.documentUrl) {
        return {
            ok: true,
            async arrayBuffer() { return Uint8Array.from([37, 80, 68, 70]).buffer; },
        };
    }
    camposSubidos = [...options.body.keys()];

    return { ok: true, async json() { return { message: 'Firma aceptada' }; } };
};

const exitoso = componente(certificado);
await exitoso.firmar();
assert.equal(exitoso.error, '', exitoso.error);
assert.deepEqual(camposSubidos.sort(), ['original_referencia', 'original_sha256', 'pdf_firmado']);
assert.equal(exitoso.$refs.clave.value, '');
assert.equal(exitoso.$refs.certificado.value, '');
assert.equal(exitoso.estado, 'completado');

assert.equal(/localStorage|sessionStorage|indexedDB/u.test(window.hubDigitalFirmador.toString()), false);
process.stdout.write('Firmador UI: campos sensibles limpiados y carga limitada al PDF firmado y referencias.\n');
