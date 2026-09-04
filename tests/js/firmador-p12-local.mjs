import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { PDFDocument, StandardFonts } from 'pdf-lib';

const [rutaP12, rutaCredenciales] = process.argv.slice(2);

if (!rutaP12 || !rutaCredenciales) {
    throw new Error('Uso: node tests/js/firmador-p12-local.mjs <certificado.p12> <credenciales.txt>');
}

const p12Bytes = readFileSync(rutaP12);
const lineasCredenciales = readFileSync(rutaCredenciales, 'utf8')
    .split(/\r?\n/u)
    .map((linea) => linea.trim())
    .filter(Boolean);
const candidatosClave = [...new Set(lineasCredenciales.flatMap((linea) => {
    const partes = linea.split(/\s+/u).filter(Boolean);

    return [linea, partes.at(-1), partes.slice(1).join(' ')].filter(Boolean);
}))];

assert.ok(candidatosClave.length > 0, 'El archivo de credenciales no contiene una clave utilizable.');

const documento = await PDFDocument.create();
const pagina = documento.addPage([595, 842]);
const fuente = await documento.embedFont(StandardFonts.Helvetica);
pagina.drawText('Validacion local del Firmador HubDigital', {
    x: 48,
    y: 790,
    size: 12,
    font: fuente,
});
const pdfBytes = await documento.save();

let respuesta;
globalThis.self = {
    postMessage(mensaje) {
        respuesta = mensaje;
    },
};

await import('../../resources/js/workers/pdf-signing.worker.js');

for (const clave of candidatosClave) {
    respuesta = undefined;
    await globalThis.self.onmessage({
        data: {
            pdf: pdfBytes.buffer.slice(pdfBytes.byteOffset, pdfBytes.byteOffset + pdfBytes.byteLength),
            p12: p12Bytes.buffer.slice(p12Bytes.byteOffset, p12Bytes.byteOffset + p12Bytes.byteLength),
            passphrase: clave,
            reason: 'Validacion local del Firmador HubDigital',
            location: 'Quito, Ecuador',
        },
    });

    if (respuesta?.ok === true) {
        break;
    }
}

assert.equal(respuesta?.ok, true, 'El certificado real no pudo firmar con las credenciales proporcionadas.');

const pdfFirmado = Buffer.from(respuesta.pdf);
assert.ok(pdfFirmado.length > pdfBytes.length, 'El PDF firmado debe incluir el contenedor criptografico.');
assert.ok(pdfFirmado.includes(Buffer.from('/ByteRange')), 'La firma debe cubrir un ByteRange del PDF.');
assert.ok(
    pdfFirmado.includes(Buffer.from('/ETSI.CAdES.detached')),
    'La firma debe usar el subfiltro ETSI CAdES detached.',
);

process.stdout.write('Firmador HubDigital: certificado real validado localmente con PKCS#12 y CAdES detached.\n');
