package org.hubdigital.signatures;

import java.io.ByteArrayInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.time.Instant;
import java.net.URI;
import java.security.KeyStore;
import java.security.KeyPair;
import java.security.KeyPairGenerator;
import java.security.PrivateKey;
import java.security.Security;
import java.security.MessageDigest;
import java.security.Signature;
import java.security.cert.CertPath;
import java.security.cert.CertStore;
import java.security.cert.CertPathValidator;
import java.security.cert.CertPathValidatorException;
import java.security.cert.Certificate;
import java.security.cert.CertificateFactory;
import java.security.cert.CollectionCertStoreParameters;
import java.security.cert.PKIXParameters;
import java.security.cert.PKIXRevocationChecker;
import java.security.cert.TrustAnchor;
import java.security.cert.X509Certificate;
import java.security.cert.X509CRL;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collection;
import java.util.Date;
import java.util.Enumeration;
import java.util.EnumSet;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.math.BigInteger;
import org.apache.pdfbox.Loader;
import org.apache.pdfbox.cos.COSDictionary;
import org.apache.pdfbox.cos.COSName;
import org.apache.pdfbox.cos.COSObject;
import org.apache.pdfbox.pdmodel.PDDocument;
import org.apache.pdfbox.pdmodel.PDPage;
import org.apache.pdfbox.pdmodel.interactive.digitalsignature.PDSignature;
import org.bouncycastle.cert.X509CertificateHolder;
import org.bouncycastle.cert.jcajce.JcaCertStore;
import org.bouncycastle.cert.jcajce.JcaX509CertificateConverter;
import org.bouncycastle.cert.jcajce.JcaX509v3CertificateBuilder;
import org.bouncycastle.cms.CMSProcessableByteArray;
import org.bouncycastle.cms.CMSSignedData;
import org.bouncycastle.cms.CMSSignedDataGenerator;
import org.bouncycastle.cms.SignerInformation;
import org.bouncycastle.cms.jcajce.JcaSignerInfoGeneratorBuilder;
import org.bouncycastle.cms.jcajce.JcaSimpleSignerInfoVerifierBuilder;
import org.bouncycastle.jce.provider.BouncyCastleProvider;
import org.bouncycastle.operator.jcajce.JcaContentSignerBuilder;
import org.bouncycastle.operator.jcajce.JcaDigestCalculatorProviderBuilder;
import org.bouncycastle.asn1.x500.X500Name;
import org.bouncycastle.asn1.ASN1OctetString;
import org.bouncycastle.asn1.cms.Attribute;
import org.bouncycastle.asn1.cms.AttributeTable;
import org.bouncycastle.asn1.cms.CMSAttributes;
import org.bouncycastle.asn1.pkcs.PKCSObjectIdentifiers;

/** Firma y valida el CMS separado incrustado en un PDF; no depende de Windows. */
public final class PdfSignatureCli {
    private static volatile Set<TrustAnchor> cachedAnchors;
    private PdfSignatureCli() {}

    public static void main(String[] args) {
        Security.addProvider(new BouncyCastleProvider());
        Security.setProperty("ocsp.enable", "true");
        System.setProperty("com.sun.security.enableCRLDP", "true");
        try {
            if (args.length == 2 && "verify".equals(args[0])) {
                emit(verify(Path.of(args[1])));
            } else if (args.length == 2 && "inspect".equals(args[0])) {
                emit(inspect(Path.of(args[1])));
            } else if (args.length == 2 && "inspect-crl".equals(args[0])) {
                emit(inspectCrl(Path.of(args[1])));
            } else if (args.length == 5 && "sign".equals(args[0])) {
                sign(Path.of(args[1]), Path.of(args[2]), Path.of(args[3]), Path.of(args[4]));
                emit(new Result("firmado", "Firma CMS creada en el PDF.", true));
            } else if (args.length == 1 && "selftest".equals(args[0])) {
                selftest();
                emit(new Result("firmado", "Autoprueba Java: firma genuina, alteración y ausencia de firma comprobadas.", true));
            } else {
                emit(new Result("verificacion_no_disponible", "Uso: verify PDF | sign ENTRADA SALIDA P12 ARCHIVO_CLAVE", false));
                System.exit(2);
            }
        } catch (Exception e) {
            diagnosticarError(e);
            emit(new Result("verificacion_no_disponible", "No fue posible completar la operación PDF. Revisa el archivo y la configuración del validador.", false));
            System.exit(2);
        }
    }

    private static Result verify(Path path) throws Exception {
        long inicio = System.nanoTime();
        byte[] bytes = Files.readAllBytes(path);
        try (PDDocument pdf = Loader.loadPDF(bytes)) {
            diagnostico("abrir_pdf", inicio);
            List<PDSignature> signatures = pdf.getSignatureDictionaries();
            if (signatures.isEmpty()) return new Result("sin_firma", "El PDF no contiene firma digital.", false);
            RevocationResolver.Evidencia evidencia = RevocationResolver.extraer(pdf);
            signatures.sort((a, b) -> Long.compare(
                (long) a.getByteRange()[2] + a.getByteRange()[3],
                (long) b.getByteRange()[2] + b.getByteRange()[3]));
            boolean missingRevocationSource = false;
            String revocationStatus = "NO_REVOCADO";
            ExecutorService workers = Executors.newFixedThreadPool(Math.min(4, signatures.size()));
            try {
                List<Future<Result>> results = new ArrayList<>();
                for (int index = 0; index < signatures.size(); index++) {
                    final int current = index;
                    results.add(workers.submit(() -> verifyOne(bytes, signatures.get(current),
                        current == signatures.size() - 1, evidencia)));
                }
                for (int index = 0; index < results.size(); index++) {
                    Result result = results.get(index).get();
                    if ("firmado_sin_revocacion".equals(result.status)) {
                        missingRevocationSource = true;
                        if ("NO_REVOCADO".equals(revocationStatus)
                            || "NO_DISPONIBLE".equals(result.estadoRevocacion)) revocationStatus = result.estadoRevocacion;
                    } else if (!"firmado".equals(result.status)) {
                        return new Result(result.status, "Firma " + (index + 1) + ": " + result.reason,
                            result.cryptographicallyValid, result.estadoRevocacion);
                    }
                }
            } finally {
                workers.shutdownNow();
            }
            return new Result(missingRevocationSource ? "firmado_sin_revocacion" : "firmado",
                "Se verificaron criptográficamente " + signatures.size() + " firma(s)."
                    + (missingRevocationSource ? " El estado de revocación no pudo comprobarse en al menos una firma." : ""),
                true, revocationStatus);
        } catch (org.apache.pdfbox.pdmodel.encryption.InvalidPasswordException e) {
            return new Result("firma_invalida", "El PDF está cifrado y no se puede validar.", false);
        } catch (Exception e) {
            diagnosticarError(e);
            return new Result("firma_invalida", "La estructura de firma PDF o CMS no es válida.", false);
        }
    }

    private static Result inspectCrl(Path path) {
        try (InputStream in = Files.newInputStream(path)) {
            X509CRL crl = (X509CRL) CertificateFactory.getInstance("X.509").generateCRL(in);
            Date now = new Date();
            if (crl.getThisUpdate() == null || crl.getThisUpdate().after(now)
                || crl.getNextUpdate() == null || !crl.getNextUpdate().after(now)) {
                return new Result("crl_caducada", "La CRL no está vigente.", false);
            }
            return new Result("crl_vigente", "La CRL tiene formato y vigencia correctos.", true);
        } catch (Exception e) {
            return new Result("crl_invalida", "El archivo no contiene una CRL X.509 válida.", false);
        }
    }

    private static Result inspect(Path path) {
        try (PDDocument pdf = Loader.loadPDF(Files.readAllBytes(path))) {
            if (pdf.isEncrypted() || pdf.getNumberOfPages() < 1) {
                return new Result("archivo_inseguro", "El PDF está cifrado o no contiene páginas legibles.", false);
            }
            Set<String> forbidden = Set.of("JavaScript", "JS", "OpenAction", "AA", "Launch",
                "EmbeddedFile", "EmbeddedFiles", "RichMedia", "Rendition", "XFA", "GoToR",
                "SubmitForm", "ImportData", "FileAttachment");
            for (var objectKey : pdf.getDocument().getXrefTable().keySet()) {
                COSObject object = pdf.getDocument().getObjectFromPool(objectKey);
                if (!(object.getObject() instanceof COSDictionary dictionary)) continue;
                for (COSName key : dictionary.keySet()) {
                    if (forbidden.contains(key.getName())) {
                        return new Result("archivo_inseguro", "El PDF contiene acciones o contenido activo: " + key.getName(), false);
                    }
                }
                String action = dictionary.getNameAsString(COSName.S);
                if (action != null && forbidden.contains(action)) {
                    return new Result("archivo_inseguro", "El PDF contiene una acción activa: " + action, false);
                }
            }
            return new Result("seguro", "Estructura PDF sin JavaScript ni contenido activo.", true);
        } catch (Exception e) {
            diagnosticarError(e);
            return new Result("archivo_inseguro", "El PDF no pudo abrirse de forma segura.", false);
        }
    }

    private static Result verifyOne(byte[] bytes, PDSignature signature, boolean last,
        RevocationResolver.Evidencia evidencia) throws Exception {
            long inicio = System.nanoTime();
            int[] range = signature.getByteRange();
            if (range == null || range.length != 4 || range[0] != 0 || range[1] < 1
                || range[2] <= range[1] || range[3] < 1 || (long) range[2] + range[3] > bytes.length
                || (last && (long) range[2] + range[3] != bytes.length)) {
                return new Result("firma_invalida", "La firma no cubre el PDF completo.", false);
            }
            byte[] signed = signature.getSignedContent(bytes);
            byte[] contents = signature.getContents(bytes);
            diagnostico("extraer_contenido_firmado", inicio);
            CMSSignedData cms = new CMSSignedData(new CMSProcessableByteArray(signed), new ByteArrayInputStream(contents));
            diagnostico("leer_cms", inicio);
            var crlsCms = cms.getCRLs().getMatches(null);
            if (!crlsCms.isEmpty()) {
                List<byte[]> crls = new ArrayList<>(evidencia.crl());
                for (var crl : crlsCms) {
                    if (crls.size() >= 32) break;
                    byte[] encoded = crl.getEncoded();
                    if (encoded.length <= 10_000_000) crls.add(encoded);
                }
                evidencia = new RevocationResolver.Evidencia(evidencia.ocsp(), crls);
            }
            Collection<SignerInformation> signers = cms.getSignerInfos().getSigners();
            if (signers.size() != 1) return new Result("firma_invalida", "El CMS no tiene un firmante único.", false);
            SignerInformation signer = signers.iterator().next();
            Collection<X509CertificateHolder> matches = cms.getCertificates().getMatches(signer.getSID());
            if (matches.size() != 1) return new Result("firma_invalida", "No se encontró el certificado del firmante.", false);
            X509Certificate certificate = (X509Certificate) CertificateFactory.getInstance("X.509")
                .generateCertificate(new ByteArrayInputStream(matches.iterator().next().getEncoded()));
            diagnostico("extraer_certificado", inicio);
            if (!verificarCms(signer, certificate, signed)) {
                return new Result("firma_invalida", "Falló la comprobación criptográfica del contenido firmado.", false);
            }
            diagnostico("firma_criptografica", inicio);
            Date fechaValidacion = new Date();
            AttributeTable unsigned = signer.getUnsignedAttributes();
            if (unsigned != null && unsigned.get(PKCSObjectIdentifiers.id_aa_signatureTimeStampToken) != null) {
                Date fechaConfiable = MarcaTiempoConfiable.obtener(signer, trustAnchors());
                if (fechaConfiable != null) fechaValidacion = fechaConfiable;
            }
            try {
                certificate.checkValidity(fechaValidacion);
            } catch (java.security.cert.CertificateExpiredException e) {
                return new Result("certificado_caducado", "El certificado está caducado y no hay un sello de tiempo confiable durante su vigencia.", true);
            } catch (java.security.cert.CertificateNotYetValidException e) {
                return new Result("certificado_aun_no_vigente", "El certificado aún no era válido en la fecha comprobada.", true);
            }
            return validateChain(certificate, cms, evidencia, fechaValidacion);
    }

    private static boolean verificarCms(SignerInformation signer, X509Certificate certificate, byte[] signed)
        throws Exception {
        long inicio = System.nanoTime();
        String algoritmo = switch (signer.getEncryptionAlgOID()) {
            case "1.2.840.113549.1.1.11" -> "SHA256withRSA";
            case "1.2.840.113549.1.1.12" -> "SHA384withRSA";
            case "1.2.840.113549.1.1.13" -> "SHA512withRSA";
            case "1.2.840.10045.4.3.2" -> "SHA256withECDSA";
            case "1.2.840.10045.4.3.3" -> "SHA384withECDSA";
            case "1.2.840.10045.4.3.4" -> "SHA512withECDSA";
            default -> null;
        };
        String hash = switch (signer.getDigestAlgOID()) {
            case "2.16.840.1.101.3.4.2.1" -> "SHA-256";
            case "2.16.840.1.101.3.4.2.2" -> "SHA-384";
            case "2.16.840.1.101.3.4.2.3" -> "SHA-512";
            default -> null;
        };
        if (algoritmo == null || hash == null || !algoritmo.replace("-", "").startsWith(hash.replace("-", ""))) {
            diagnostico("cms_ruta_generica", inicio);
            return signer.verify(new JcaSimpleSignerInfoVerifierBuilder().build(certificate));
        }
        AttributeTable attributes = signer.getSignedAttributes();
        byte[] signedBytes = signed;
        if (attributes != null) {
            Attribute digestAttribute = attributes.get(CMSAttributes.messageDigest);
            Attribute contentAttribute = attributes.get(CMSAttributes.contentType);
            if (digestAttribute == null || digestAttribute.getAttrValues().size() != 1
                || contentAttribute == null || contentAttribute.getAttrValues().size() != 1
                || !PKCSObjectIdentifiers.data.equals(contentAttribute.getAttrValues().getObjectAt(0))) return false;
            byte[] expected = ASN1OctetString.getInstance(digestAttribute.getAttrValues().getObjectAt(0)).getOctets();
            if (!MessageDigest.isEqual(expected, MessageDigest.getInstance(hash).digest(signed))) return false;
            diagnostico("cms_digest", inicio);
            signedBytes = signer.getEncodedSignedAttributes();
        }
        Signature verifier = Signature.getInstance(algoritmo);
        diagnostico("cms_instancia_jca", inicio);
        var publicKey = certificate.getPublicKey();
        diagnostico("cms_clave_publica", inicio);
        verifier.initVerify(publicKey);
        diagnostico("cms_iniciar_verificador", inicio);
        verifier.update(signedBytes);
        diagnostico("cms_previo_firma", inicio);
        return verifier.verify(signer.getSignature());
    }

    private static Result validateChain(X509Certificate signer, CMSSignedData cms,
        RevocationResolver.Evidencia evidencia, Date fechaValidacion) throws Exception {
        long inicio = System.nanoTime();
        String issuerName = signer.getIssuerX500Principal().getName().toUpperCase(java.util.Locale.ROOT);
        Set<TrustAnchor> anchors = trustAnchors();
        if (anchors.isEmpty()) return new Result("certificado_no_confiable", "No hay autoridades de confianza configuradas.", true);
        diagnostico("almacen_confianza", inicio);
        List<X509Certificate> available = new ArrayList<>();
        for (X509CertificateHolder holder : cms.getCertificates().getMatches(null)) {
            available.add((X509Certificate) CertificateFactory.getInstance("X.509")
                .generateCertificate(new ByteArrayInputStream(holder.getEncoded())));
        }
        List<X509Certificate> chain = new ArrayList<>();
        chain.add(signer);
        while (chain.size() <= available.size()) {
            X509Certificate child = chain.get(chain.size() - 1);
            if (anchors.stream().anyMatch(a -> a.getTrustedCert().equals(child))) break;
            X509Certificate issuer = available.stream().filter(c -> !chain.contains(c)
                && child.getIssuerX500Principal().equals(c.getSubjectX500Principal())).findFirst().orElse(null);
            if (issuer == null) break;
            chain.add(issuer);
        }
        if (anchors.stream().anyMatch(a -> a.getTrustedCert().equals(chain.get(chain.size() - 1)))) {
            chain.remove(chain.size() - 1);
        }
        if (chain.isEmpty()) return new Result("firmado_sin_revocacion",
            "La firma y el certificado raíz son íntegros; su revocación no está comprobada.", true, "NO_COMPROBADO");
        X509Certificate top = chain.get(chain.size() - 1);
        X509Certificate rootForTop = null;
        for (TrustAnchor anchor : anchors) {
            X509Certificate root = anchor.getTrustedCert();
            if (!top.getIssuerX500Principal().equals(root.getSubjectX500Principal())) continue;
            try {
                top.verify(root.getPublicKey());
                rootForTop = root;
                break;
            } catch (Exception ignored) { /* otra autoridad con igual nombre no firma este certificado */ }
        }
        if (rootForTop == null) return new Result("almacen_incompleto", "Falta el certificado emisor o raíz en el almacén de confianza.", true);
        CertPath path = CertificateFactory.getInstance("X.509").generateCertPath(chain);
        PKIXParameters params = new PKIXParameters(anchors);
        params.setRevocationEnabled(false);
        params.setDate(fechaValidacion);
        CertPathValidator validator = CertPathValidator.getInstance("PKIX");
        try {
            validator.validate(path, params);
            diagnostico("cadena_pkix", inicio);
        } catch (CertPathValidatorException e) {
            if (e.getReason() == CertPathValidatorException.BasicReason.EXPIRED
                || e.getReason() == CertPathValidatorException.BasicReason.NOT_YET_VALID) {
                return new Result("certificado_caducado", "Un certificado de la cadena no está vigente.", true);
            }
            diagnosticarError(e);
            return new Result("certificado_no_confiable", "La cadena de certificados no supera la validación X.509 de confianza.", true);
        }
        URI responderExcepcional = fuenteExcepcional(issuerName, "HUBDIGITAL_SIGNATURE_OCSP_RESPONDERS");
        URI crlExcepcional = fuenteExcepcional(issuerName, "HUBDIGITAL_SIGNATURE_CRL_OVERRIDES");
        int timeout = Integer.getInteger("hubdigital.revocation.timeout", 2);
        X509Certificate issuer = chain.size() > 1 ? chain.get(1) : rootForTop;
        RevocationResolver.Resultado revocation = RevocationResolver.resolver(signer, issuer, path,
            anchors, evidencia, responderExcepcional, crlExcepcional, fechaValidacion,
            Math.max(1, Math.min(timeout, 5)));
        diagnostico("revocacion", inicio);
        if (revocation.estado() == RevocationResolver.Estado.REVOCADO) {
            return new Result("certificado_revocado", revocation.fuente(), true, "REVOCADO");
        }
        if (revocation.estado() == RevocationResolver.Estado.NO_REVOCADO) {
            return new Result("firmado", "Firma, integridad y cadena válidas; revocación comprobada mediante "
                + revocation.fuente() + ".", true, "NO_REVOCADO");
        }
        return new Result("firmado_sin_revocacion", "Firma, integridad y cadena válidas. "
            + revocation.fuente(), true, revocation.estado().name());
    }

    static synchronized Set<TrustAnchor> trustAnchors() throws Exception {
        if (cachedAnchors != null) return cachedAnchors;
        Set<TrustAnchor> anchors = new HashSet<>();
        KeyStore trust = KeyStore.getInstance(KeyStore.getDefaultType());
        String configured = System.getenv("HUBDIGITAL_SIGNATURE_TRUSTSTORE");
        if (configured != null && !configured.isBlank()) {
            try (InputStream in = Files.newInputStream(Path.of(configured))) {
                String password = System.getenv("HUBDIGITAL_SIGNATURE_TRUSTSTORE_PASSWORD");
                trust.load(in, password == null ? new char[0] : password.toCharArray());
            }
        } else {
            Path cacerts = Path.of(System.getProperty("java.home"), "lib", "security", "cacerts");
            try (InputStream in = Files.newInputStream(cacerts)) { trust.load(in, "changeit".toCharArray()); }
        }
        Enumeration<String> aliases = trust.aliases();
        while (aliases.hasMoreElements()) {
            Certificate cert = trust.getCertificate(aliases.nextElement());
            if (cert instanceof X509Certificate x509) anchors.add(new TrustAnchor(x509, null));
        }
        String configuredDir = System.getenv("HUBDIGITAL_SIGNATURE_TRUST_DIR");
        Path trustDir = configuredDir == null || configuredDir.isBlank()
            ? Path.of(PdfSignatureCli.class.getProtectionDomain().getCodeSource().getLocation().toURI())
                .getParent().resolve("../signature-trust").normalize()
            : Path.of(configuredDir);
        if (Files.isDirectory(trustDir)) {
            try (var files = Files.list(trustDir)) {
                for (Path file : files.filter(p -> p.getFileName().toString().matches("(?i).*\\.(pem|cer|crt)" )).toList()) {
                    try (InputStream in = Files.newInputStream(file)) {
                        for (Certificate cert : CertificateFactory.getInstance("X.509").generateCertificates(in)) {
                            if (cert instanceof X509Certificate root && root.getBasicConstraints() >= 0
                                && root.getSubjectX500Principal().equals(root.getIssuerX500Principal())) {
                                root.verify(root.getPublicKey());
                                anchors.add(new TrustAnchor(root, null));
                            }
                        }
                    }
                }
            }
        }
        cachedAnchors = Set.copyOf(anchors);
        return cachedAnchors;
    }

    private static URI fuenteExcepcional(String issuerName, String variable) {
        String configured = System.getenv(variable);
        if (configured == null) return null;
        for (String entry : configured.split("\\|")) {
            int separator = entry.indexOf('=');
            if (separator <= 0 || !issuerName.contains(entry.substring(0, separator).trim().toUpperCase(java.util.Locale.ROOT))) continue;
            try { return URI.create(entry.substring(separator + 1).trim()); }
            catch (IllegalArgumentException ignored) { return null; }
        }
        return null;
    }

    private static void diagnostico(String fase, long inicio) {
        if ("1".equals(System.getenv("HUBDIGITAL_SIGNATURE_DIAGNOSTICS"))) {
            System.err.println(fase + "_ms=" + (System.nanoTime() - inicio) / 1_000_000);
        }
    }

    private static void diagnosticarError(Exception error) {
        if ("1".equals(System.getenv("HUBDIGITAL_SIGNATURE_DIAGNOSTICS"))) {
            error.printStackTrace(System.err);
        }
    }

    private static void sign(Path input, Path output, Path p12, Path passwordFile) throws Exception {
        String raw = Files.readString(passwordFile, StandardCharsets.UTF_8);
        KeyStore keystore = null;
        String password = null;
        for (String line : raw.split("\\R")) {
            line = line.trim();
            if (line.isEmpty()) continue;
            String[] words = line.split("\\s+");
            List<String> candidates = new ArrayList<>();
            candidates.add(line);
            candidates.add(words[words.length - 1]);
            if (words.length > 1) candidates.add(String.join(" ", Arrays.copyOfRange(words, 1, words.length)));
            if (line.contains("=")) candidates.add(line.substring(line.indexOf('=') + 1).trim());
            for (String candidate : candidates) {
                try (InputStream in = Files.newInputStream(p12)) {
                    KeyStore opened = KeyStore.getInstance("PKCS12");
                    opened.load(in, candidate.toCharArray());
                    keystore = opened;
                    password = candidate;
                    break;
                } catch (java.io.IOException ignored) { /* probar la siguiente forma de la clave */ }
            }
            if (keystore != null) break;
        }
        if (keystore == null) throw new IllegalArgumentException("No se pudo abrir el P12 con la credencial proporcionada.");
        String alias = null;
        Enumeration<String> aliases = keystore.aliases();
        while (aliases.hasMoreElements()) {
            String candidate = aliases.nextElement();
            if (keystore.isKeyEntry(candidate)) { alias = candidate; break; }
        }
        if (alias == null) throw new IllegalArgumentException("El P12 no tiene clave privada.");
        PrivateKey key = (PrivateKey) keystore.getKey(alias, password.toCharArray());
        Certificate[] chain = keystore.getCertificateChain(alias);
        X509Certificate signer = (X509Certificate) chain[0];
        signer.checkValidity(new Date());
        List<X509Certificate> certificates = Arrays.stream(chain).map(c -> (X509Certificate) c).toList();
        Files.createDirectories(output.toAbsolutePath().getParent());
        try (PDDocument pdf = Loader.loadPDF(input.toFile()); FileOutputStream out = new FileOutputStream(output.toFile())) {
            PDSignature signature = new PDSignature();
            signature.setFilter(PDSignature.FILTER_ADOBE_PPKLITE);
            signature.setSubFilter(PDSignature.SUBFILTER_ETSI_CADES_DETACHED);
            signature.setName(signer.getSubjectX500Principal().getName());
            signature.setSignDate(java.util.Calendar.getInstance());
            pdf.addSignature(signature, content -> {
                try {
                    CMSSignedDataGenerator generator = new CMSSignedDataGenerator();
                    String algorithm = "EC".equalsIgnoreCase(key.getAlgorithm()) ? "SHA256withECDSA" : "SHA256withRSA";
                    generator.addSignerInfoGenerator(new JcaSignerInfoGeneratorBuilder(
                        new JcaDigestCalculatorProviderBuilder().setProvider("BC").build()
                    ).build(new JcaContentSignerBuilder(algorithm).setProvider("BC").build(key), signer));
                    generator.addCertificates(new JcaCertStore(certificates));
                    return generator.generate(new CMSProcessableByteArray(content.readAllBytes()), false).getEncoded();
                } catch (Exception e) { throw new java.io.IOException("No se pudo crear CMS", e); }
            });
            pdf.saveIncremental(out);
        }
    }

    private static void selftest() throws Exception {
        Path temp = Files.createTempDirectory("hubdigital-pdf-signature-");
        try {
            Path unsigned = temp.resolve("sin-firma.pdf");
            Path signed = temp.resolve("firmado.pdf");
            Path altered = temp.resolve("alterado.pdf");
            Path p12 = temp.resolve("prueba.p12");
            Path password = temp.resolve("clave.txt");
            try (PDDocument pdf = new PDDocument()) {
                pdf.addPage(new PDPage());
                pdf.save(unsigned.toFile());
            }
            KeyPairGenerator keygen = KeyPairGenerator.getInstance("RSA");
            keygen.initialize(2048);
            KeyPair keys = keygen.generateKeyPair();
            X500Name name = new X500Name("C=EC,O=HubDigital QA,CN=Prueba criptográfica");
            Date from = Date.from(Instant.now().minusSeconds(60));
            Date to = Date.from(Instant.now().plusSeconds(3600));
            X509Certificate cert = new JcaX509CertificateConverter().setProvider("BC").getCertificate(
                new JcaX509v3CertificateBuilder(name, BigInteger.valueOf(System.nanoTime()).abs(),
                    from, to, name, keys.getPublic())
                    .build(new JcaContentSignerBuilder("SHA256withRSA").setProvider("BC").build(keys.getPrivate())));
            KeyStore store = KeyStore.getInstance("PKCS12");
            store.load(null, null);
            store.setKeyEntry("qa", keys.getPrivate(), "temporal".toCharArray(), new Certificate[] { cert });
            try (var out = Files.newOutputStream(p12)) { store.store(out, "temporal".toCharArray()); }
            Files.writeString(password, "temporal", StandardCharsets.UTF_8);

            sign(unsigned, signed, p12, password);
            cachedAnchors = Set.of(new TrustAnchor(cert, null));
            Result genuino = verify(signed);
            if (!"firmado_sin_revocacion".equals(genuino.status) || !genuino.cryptographicallyValid)
                throw new IllegalStateException("Una firma genuina sin fuente de revocación no fue aceptada.");
            byte[] changed = Files.readAllBytes(signed);
            changed[7] = changed[7] == '6' ? (byte) '7' : (byte) '6';
            Files.write(altered, changed);
            if (!"firma_invalida".equals(verify(altered).status)) throw new IllegalStateException("Se aceptó un PDF alterado.");
            if (!"sin_firma".equals(verify(unsigned).status)) throw new IllegalStateException("Se aceptó un PDF sin firma.");
        } finally {
            try (var files = Files.list(temp)) {
                for (Path file : files.toList()) Files.deleteIfExists(file);
            }
            Files.deleteIfExists(temp);
        }
    }

    private record Result(String status, String reason, boolean cryptographicallyValid, String estadoRevocacion) {
        Result(String status, String reason, boolean cryptographicallyValid) {
            this(status, reason, cryptographicallyValid,
                "certificado_revocado".equals(status) ? "REVOCADO" : "NO_COMPROBADO");
        }
    }

    private static void emit(Result result) {
        boolean valido = "firmado".equals(result.status) || "firmado_sin_revocacion".equals(result.status);
        boolean indeterminado = "verificacion_no_disponible".equals(result.status)
            || "seguro".equals(result.status) || result.status.startsWith("crl_");
        String certificado = switch (result.status) {
            case "certificado_revocado" -> "REVOCADO";
            case "certificado_caducado" -> "CADUCADO";
            case "certificado_aun_no_vigente" -> "AUN_NO_VIGENTE";
            case "certificado_no_confiable", "almacen_incompleto" -> "NO_CONFIABLE";
            default -> valido ? "VALIDO" : "NO_COMPROBADO";
        };
        System.out.println("{\"status\":\"" + escape(result.status) + "\",\"reason\":\""
            + escape(result.reason) + "\",\"cryptographically_valid\":" + result.cryptographicallyValid
            + ",\"estado_documento\":\"" + (valido ? "VALIDO" : (indeterminado ? "INDETERMINADO" : "INVALIDO"))
            + "\",\"estado_firma\":\"" + (indeterminado ? "NO_COMPROBADA"
                : (result.cryptographicallyValid ? "VALIDA" : "INVALIDA"))
            + "\",\"estado_certificado\":\"" + certificado
            + "\",\"estado_revocacion\":\"" + escape(result.estadoRevocacion) + "\"}");
    }

    private static String escape(String s) {
        StringBuilder result = new StringBuilder();
        for (char c : s.toCharArray()) {
            if (c == '\\' || c == '"') result.append('\\').append(c);
            else if (c < 32 || c > 126) result.append(String.format("\\u%04x", (int) c));
            else result.append(c);
        }
        return result.toString();
    }
}
