import assert from 'node:assert/strict';
import forge from 'node-forge';
import { PDFDocument, StandardFonts } from 'pdf-lib';

const clave = 'solo-prueba-hubdigital';
const llaves = forge.pki.rsa.generateKeyPair(1024);
const certificado = forge.pki.createCertificate();
certificado.publicKey = llaves.publicKey;
certificado.serialNumber = '01';
certificado.validity.notBefore = new Date(Date.now() - 60_000);
certificado.validity.notAfter = new Date(Date.now() + 86_400_000);
const nombre = [{ name: 'commonName', value: 'Consultor de prueba HubDigital' }];
certificado.setSubject(nombre);
certificado.setIssuer(nombre);
certificado.setExtensions([
    { name: 'basicConstraints', cA: false },
    { name: 'keyUsage', digitalSignature: true, nonRepudiation: true },
]);
certificado.sign(llaves.privateKey, forge.md.sha256.create());

const p12Asn1 = forge.pkcs12.toPkcs12Asn1(
    llaves.privateKey,
    [certificado],
    clave,
    { algorithm: '3des', friendlyName: 'HubDigital E2E' },
);
const p12Bytes = Uint8Array.from(
    forge.util.binary.raw.decode(forge.asn1.toDer(p12Asn1).getBytes()),
);

const documento = await PDFDocument.create();
const pagina = documento.addPage([595, 842]);
const fuente = await documento.embedFont(StandardFonts.Helvetica);
pagina.drawText('Datos deposito material MEPN - prueba integral', {
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
await globalThis.self.onmessage({
    data: {
        pdf: pdfBytes.buffer.slice(pdfBytes.byteOffset, pdfBytes.byteOffset + pdfBytes.byteLength),
        p12: p12Bytes.buffer.slice(p12Bytes.byteOffset, p12Bytes.byteOffset + p12Bytes.byteLength),
        passphrase: clave,
        reason: 'Prueba automatizada del Firmador HubDigital',
        location: 'Quito, Ecuador',
    },
});

assert.equal(respuesta?.ok, true, respuesta?.error ?? 'El worker no devolvio una firma.');
assert.equal(respuesta.certificado.nombre, 'Consultor de prueba HubDigital');

const pdfFirmado = Buffer.from(respuesta.pdf);
assert.ok(pdfFirmado.length > pdfBytes.length, 'El PDF firmado debe contener el contenedor criptografico.');
assert.ok(pdfFirmado.includes(Buffer.from('/ByteRange')), 'La firma debe cubrir un ByteRange del PDF.');
assert.ok(
    pdfFirmado.includes(Buffer.from('/ETSI.CAdES.detached')),
    'La firma debe usar el subfiltro ETSI CAdES detached.',
);

process.stdout.write('Firmador HubDigital: PDF firmado localmente con PKCS#12 y CAdES detached.\n');
