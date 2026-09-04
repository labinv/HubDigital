import { Buffer } from 'buffer';
import forge from 'node-forge';
import { PDFDocument } from 'pdf-lib';
import { pdflibAddPlaceholder } from '@signpdf/placeholder-pdf-lib';
import { P12Signer } from '@signpdf/signer-p12';
import { SignPdf } from '@signpdf/signpdf';
import { SUBFILTER_ETSI_CADES_DETACHED } from '@signpdf/utils';

globalThis.Buffer = Buffer;

function atributo(atributos, nombre) {
    return atributos.find((item) => item.name === nombre || item.shortName === nombre)?.value ?? null;
}

function abrirCertificado(bytes, clave) {
    const der = forge.util.createBuffer(Buffer.from(bytes).toString('binary'));
    const asn1 = forge.asn1.fromDer(der);
    const p12 = forge.pkcs12.pkcs12FromAsn1(asn1, false, clave);
    const certificados = p12.getBags({ bagType: forge.pki.oids.certBag })[forge.pki.oids.certBag] ?? [];
    const claves = [
        ...(p12.getBags({ bagType: forge.pki.oids.pkcs8ShroudedKeyBag })[forge.pki.oids.pkcs8ShroudedKeyBag] ?? []),
        ...(p12.getBags({ bagType: forge.pki.oids.keyBag })[forge.pki.oids.keyBag] ?? []),
    ];
    if (certificados.length === 0 || claves.length === 0) {
        throw new Error('El PKCS#12 no contiene un certificado y una clave privada utilizables.');
    }

    const clavePrivada = claves[0].key;
    const certificado = certificados.find(({ cert }) => (
        clavePrivada?.n && cert.publicKey?.n
        && clavePrivada.n.compareTo(cert.publicKey.n) === 0
        && clavePrivada.e.compareTo(cert.publicKey.e) === 0
    ))?.cert;
    if (!certificado) {
        throw new Error('No se encontró el certificado que corresponde a la clave privada.');
    }

    const usoClave = certificado.getExtension('keyUsage');
    if (usoClave && usoClave.digitalSignature !== true && usoClave.nonRepudiation !== true) {
        throw new Error('El certificado no está habilitado para firma digital.');
    }

    const ahora = new Date();
    if (ahora < certificado.validity.notBefore || ahora > certificado.validity.notAfter) {
        throw new Error('El certificado está fuera de su período de vigencia.');
    }

    return {
        nombre: atributo(certificado.subject.attributes, 'commonName') ?? 'Titular del certificado',
        identificacion: atributo(certificado.subject.attributes, 'serialNumber'),
        organizacion: atributo(certificado.subject.attributes, 'organizationName'),
        emisor: atributo(certificado.issuer.attributes, 'commonName'),
        numeroSerie: certificado.serialNumber,
        validoDesde: certificado.validity.notBefore.toISOString(),
        validoHasta: certificado.validity.notAfter.toISOString(),
        huellaSha256: forge.md.sha256.create()
            .update(forge.asn1.toDer(forge.pki.certificateToAsn1(certificado)).getBytes())
            .digest().toHex(),
    };
}

self.onmessage = async ({ data }) => {
    let p12Bytes = data.p12;
    try {
        const certificado = abrirCertificado(p12Bytes, data.passphrase);
        const fechaFirma = new Date();
        const pdf = await PDFDocument.load(new Uint8Array(data.pdf), { updateMetadata: false });

        pdflibAddPlaceholder({
            pdfDoc: pdf,
            reason: data.reason ?? 'Aceptación del documento emitido por HubDigital',
            contactInfo: '',
            name: certificado.nombre,
            location: data.location ?? 'Quito, Ecuador',
            signingTime: fechaFirma,
            signatureLength: 32768,
            subFilter: SUBFILTER_ETSI_CADES_DETACHED,
            widgetRect: [0, 0, 0, 0],
            appName: 'Firmador HubDigital',
        });

        const preparado = await pdf.save({ updateFieldAppearances: false });
        const firmador = new P12Signer(Buffer.from(p12Bytes), {
            passphrase: data.passphrase,
            asn1StrictParsing: false,
        });
        const firmado = await new SignPdf().sign(Buffer.from(preparado), firmador, fechaFirma);
        const resultado = firmado.buffer.slice(firmado.byteOffset, firmado.byteOffset + firmado.byteLength);

        self.postMessage({ ok: true, pdf: resultado, certificado }, [resultado]);
    } catch (error) {
        self.postMessage({ ok: false, error: String(error?.message ?? error) });
    } finally {
        if (p12Bytes?.byteLength) {
            new Uint8Array(p12Bytes).fill(0);
        }
        data.passphrase = '';
        data.pdf = null;
        p12Bytes = null;
    }
};
