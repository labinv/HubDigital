package org.hubdigital.signatures;

import java.io.ByteArrayInputStream;
import java.security.MessageDigest;
import java.security.cert.CertPath;
import java.security.cert.CertPathValidator;
import java.security.cert.CertificateFactory;
import java.security.cert.PKIXParameters;
import java.security.cert.TrustAnchor;
import java.security.cert.X509Certificate;
import java.time.Instant;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Set;
import org.bouncycastle.asn1.cms.Attribute;
import org.bouncycastle.asn1.cms.AttributeTable;
import org.bouncycastle.asn1.cms.ContentInfo;
import org.bouncycastle.asn1.pkcs.PKCSObjectIdentifiers;
import org.bouncycastle.cert.X509CertificateHolder;
import org.bouncycastle.cms.SignerInformation;
import org.bouncycastle.cms.jcajce.JcaSimpleSignerInfoVerifierBuilder;
import org.bouncycastle.tsp.TimeStampToken;

/** Solo acepta la fecha RFC 3161 si el token, su huella y la TSA son confiables. */
final class MarcaTiempoConfiable {
    private MarcaTiempoConfiable() {}

    static Date obtener(SignerInformation signer, Set<TrustAnchor> anchors) {
        AttributeTable unsigned = signer.getUnsignedAttributes();
        if (unsigned == null) return null;
        Attribute attribute = unsigned.get(PKCSObjectIdentifiers.id_aa_signatureTimeStampToken);
        if (attribute == null) return null;
        for (int i = 0; i < attribute.getAttrValues().size(); i++) {
            try {
                TimeStampToken token = new TimeStampToken(ContentInfo.getInstance(attribute.getAttrValues().getObjectAt(i)));
                String oid = token.getTimeStampInfo().getMessageImprintAlgOID().getId();
                String hash = switch (oid) {
                    case "1.3.14.3.2.26" -> "SHA-1";
                    case "2.16.840.1.101.3.4.2.1" -> "SHA-256";
                    case "2.16.840.1.101.3.4.2.2" -> "SHA-384";
                    case "2.16.840.1.101.3.4.2.3" -> "SHA-512";
                    default -> null;
                };
                if (hash == null || !MessageDigest.isEqual(token.getTimeStampInfo().getMessageImprintDigest(),
                    MessageDigest.getInstance(hash).digest(signer.getSignature()))) continue;
                Date fecha = token.getTimeStampInfo().getGenTime();
                if (fecha.after(Date.from(Instant.now().plusSeconds(300)))) continue;
                var holders = token.getCertificates().getMatches(token.getSID());
                if (holders.size() != 1) continue;
                X509Certificate tsa = convertir((X509CertificateHolder) holders.iterator().next());
                List<String> extended = tsa.getExtendedKeyUsage();
                if (extended == null || !extended.contains("1.3.6.1.5.5.7.3.8")) continue;
                tsa.checkValidity(fecha);
                token.validate(new JcaSimpleSignerInfoVerifierBuilder().build(tsa));
                if (cadenaConfiable(tsa, token, anchors, fecha)) return fecha;
            } catch (Exception ignored) { /* sello sin prueba criptográfica suficiente */ }
        }
        return null;
    }

    private static boolean cadenaConfiable(X509Certificate tsa, TimeStampToken token,
        Set<TrustAnchor> anchors, Date fecha) throws Exception {
        List<X509Certificate> disponibles = new ArrayList<>();
        for (X509CertificateHolder holder : token.getCertificates().getMatches(null)) {
            disponibles.add(convertir(holder));
        }
        List<X509Certificate> cadena = new ArrayList<>();
        cadena.add(tsa);
        while (cadena.size() <= disponibles.size()) {
            X509Certificate actual = cadena.get(cadena.size() - 1);
            if (anchors.stream().anyMatch(a -> a.getTrustedCert().equals(actual))) break;
            X509Certificate emisor = disponibles.stream().filter(c -> !cadena.contains(c)
                && actual.getIssuerX500Principal().equals(c.getSubjectX500Principal())).findFirst().orElse(null);
            if (emisor == null) break;
            cadena.add(emisor);
        }
        if (anchors.stream().anyMatch(a -> a.getTrustedCert().equals(cadena.get(cadena.size() - 1)))) {
            cadena.remove(cadena.size() - 1);
        }
        if (cadena.isEmpty()) return true;
        CertPath path = CertificateFactory.getInstance("X.509").generateCertPath(cadena);
        PKIXParameters params = new PKIXParameters(anchors);
        params.setRevocationEnabled(false);
        params.setDate(fecha);
        CertPathValidator.getInstance("PKIX").validate(path, params);
        return true;
    }

    private static X509Certificate convertir(X509CertificateHolder holder) throws Exception {
        return (X509Certificate) CertificateFactory.getInstance("X.509")
            .generateCertificate(new ByteArrayInputStream(holder.getEncoded()));
    }
}
