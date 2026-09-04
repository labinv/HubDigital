<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Valida PAdES con Poppler y compara el contenido visible con el PDF oficial. */
final class PdfsigValidacionFirmaElectronicaAdapter implements ValidacionFirmaElectronicaPort
{
    public function verificarFirma(string $rutaAbsoluta): ResultadoValidacionFirma
    {
        if (! file_exists($rutaAbsoluta)) {
            return ResultadoValidacionFirma::NoVerificado;
        }

        $resultado = $this->ejecutarPdfsig($rutaAbsoluta);
        if (str_contains($resultado['salida'], 'does not contain any signatures')) {
            return ResultadoValidacionFirma::SinFirma;
        }

        $firmas = $this->separarFirmas($resultado['salida']);
        if ($firmas === []) {
            return ResultadoValidacionFirma::NoVerificado;
        }

        foreach ($firmas as $firma) {
            if (! str_contains($firma, 'Signature Validation: Signature is Valid.')) {
                return ResultadoValidacionFirma::NoVerificado;
            }
        }

        // En documentos con varias firmas incrementales, la última debe cubrir
        // la revisión completa que el usuario entregó a HubDigital.
        $ultimaFirma = $firmas[array_key_last($firmas)];

        return str_contains($ultimaFirma, 'Total document signed')
            ? ResultadoValidacionFirma::Firmado
            : ResultadoValidacionFirma::NoVerificado;
    }

    public function verificarFirmaDetallada(
        string $rutaFirmadaAbsoluta,
        string $rutaOriginalAbsoluta,
    ): DetalleValidacionFirma {
        if (! file_exists($rutaFirmadaAbsoluta) || ! file_exists($rutaOriginalAbsoluta)) {
            return $this->fallo('No se encontro el PDF firmado o el original.');
        }

        try {
            $resultado = $this->ejecutarPdfsig($rutaFirmadaAbsoluta);
            $salida = $resultado['salida'];

            if (str_contains($salida, 'does not contain any signatures')) {
                return new DetalleValidacionFirma(
                    ResultadoValidacionFirma::SinFirma,
                    false,
                    false,
                    false,
                    false,
                    false,
                    error: 'El PDF no contiene una firma electronica.',
                );
            }

            $firmas = $this->separarFirmas($salida);
            if (count($firmas) !== 1) {
                return new DetalleValidacionFirma(
                    count($firmas) > 0 ? ResultadoValidacionFirma::Firmado : ResultadoValidacionFirma::NoVerificado,
                    false,
                    false,
                    false,
                    false,
                    false,
                    error: count($firmas) > 1
                        ? 'El documento debe contener exactamente una firma electrónica final.'
                        : 'No se pudo identificar la firma electrónica del documento.',
                );
            }

            $salidaFirma = $firmas[0];

            $integridad = str_contains($salidaFirma, 'Signature Validation: Signature is Valid.');
            $completo = str_contains($salidaFirma, 'Total document signed');
            $confiable = str_contains($salidaFirma, 'Certificate Validation: Certificate is Trusted.');
            $vigente = ! preg_match('/Certificate Validation:.*(Expired|Not Yet Valid)/i', $salidaFirma);
            $coincide = $this->contenidoVisibleCoincide($rutaOriginalAbsoluta, $rutaFirmadaAbsoluta);

            $certificado = [
                'nombre' => $this->capturar($salidaFirma, 'Signer Certificate Common Name'),
                'distinguished_name' => $this->capturar($salidaFirma, 'Signer full Distinguished Name'),
                'entidad_emisora' => $this->capturar($salidaFirma, 'Signing Certificate Authority'),
                'fecha_firma' => $this->capturar($salidaFirma, 'Signing Time'),
                'algoritmo_hash' => $this->capturar($salidaFirma, 'Signing Hash Algorithm'),
                'tipo_firma' => $this->capturar($salidaFirma, 'Signature Type'),
            ];

            $firmado = str_contains($salidaFirma, 'Signature #');
            $formatoAceptado = strtolower(trim((string) $certificado['tipo_firma'])) === 'etsi.cades.detached';

            return new DetalleValidacionFirma(
                $firmado ? ResultadoValidacionFirma::Firmado : ResultadoValidacionFirma::NoVerificado,
                $integridad,
                $completo,
                $coincide,
                $vigente,
                $confiable,
                $certificado,
                ! $formatoAceptado
                    ? 'La firma debe usar el formato ETSI CAdES detached.'
                    : ((! $resultado['exitosa'] && ! $firmado) ? trim($salida) : null),
            );
        } catch (\Throwable $e) {
            Log::warning('ValidacionFirma: fallo al validar PDF firmado', [
                'archivo' => $rutaFirmadaAbsoluta,
                'error' => $e->getMessage(),
            ]);

            return $this->fallo('No fue posible completar la validacion criptografica.');
        }
    }

    /** @return array{salida: string, exitosa: bool} */
    private function ejecutarPdfsig(string $ruta): array
    {
        $binary = $this->binario('pdfsig_binary', 'pdfsig', '/usr/bin/pdfsig');
        $comando = [$binary];
        $nssDir = config('firma-electronica.nss_dir');
        if (is_string($nssDir) && trim($nssDir) !== '') {
            $comando[] = '-nssdir';
            $comando[] = trim($nssDir);
        }
        $comando[] = $ruta;

        // No se usa -no-ocsp: pdfsig debe consultar OCSP y las CRL disponibles
        // en la base NSS configurada para detectar certificados revocados.
        $process = new Process($comando);
        $process->setEnv($this->entorno());
        $process->setTimeout(20);
        $process->run();

        return [
            'salida' => $process->getOutput().$process->getErrorOutput(),
            'exitosa' => $process->isSuccessful(),
        ];
    }

    private function contenidoVisibleCoincide(string $original, string $firmado): bool
    {
        $directorio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hubdigital-firma-'.bin2hex(random_bytes(8));
        if (! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            throw new \RuntimeException('No se pudo crear el directorio de validacion.');
        }

        try {
            $infoOriginal = $this->informacionPdf($original);
            $infoFirmado = $this->informacionPdf($firmado);
            $maxPaginas = (int) config('firma-electronica.max_pages', 200);
            if ($infoOriginal['paginas'] < 1
                || $infoOriginal['paginas'] > $maxPaginas
                || $infoOriginal !== $infoFirmado
            ) {
                return false;
            }

            $textoOriginal = $directorio.DIRECTORY_SEPARATOR.'original.txt';
            $textoFirmado = $directorio.DIRECTORY_SEPARATOR.'firmado.txt';
            $pdftotext = $this->binario('pdftotext_binary', 'pdftotext', '/usr/bin/pdftotext');

            $this->ejecutar([$pdftotext, '-layout', $original, $textoOriginal]);
            $this->ejecutar([$pdftotext, '-layout', $firmado, $textoFirmado]);
            if (! hash_equals(hash_file('sha256', $textoOriginal), hash_file('sha256', $textoFirmado))) {
                return false;
            }

            $pdftoppm = $this->binario('pdftoppm_binary', 'pdftoppm', '/usr/bin/pdftoppm');
            $dpi = (string) ((int) config('firma-electronica.render_dpi', 110));
            $this->ejecutar([$pdftoppm, '-r', $dpi, '-png', $original, $directorio.DIRECTORY_SEPARATOR.'original']);
            $this->ejecutar([$pdftoppm, '-r', $dpi, '-png', $firmado, $directorio.DIRECTORY_SEPARATOR.'firmado']);

            $originales = glob($directorio.DIRECTORY_SEPARATOR.'original-*.png') ?: [];
            $firmados = glob($directorio.DIRECTORY_SEPARATOR.'firmado-*.png') ?: [];
            if (count($originales) === 0 || count($originales) !== count($firmados)) {
                return false;
            }

            sort($originales);
            sort($firmados);
            foreach ($originales as $indice => $imagen) {
                if (! hash_equals(hash_file('sha256', $imagen), hash_file('sha256', $firmados[$indice]))) {
                    return false;
                }
            }

            return true;
        } finally {
            foreach (glob($directorio.DIRECTORY_SEPARATOR.'*') ?: [] as $archivo) {
                @unlink($archivo);
            }
            @rmdir($directorio);
        }
    }

    /** @return array{paginas: int, tamano_pagina: string} */
    private function informacionPdf(string $ruta): array
    {
        $pdfinfo = $this->binario('pdfinfo_binary', 'pdfinfo', '/usr/bin/pdfinfo');
        $process = new Process([$pdfinfo, $ruta]);
        $process->setEnv($this->entorno());
        $process->setTimeout(15);
        $process->mustRun();
        $salida = $process->getOutput();

        $paginas = preg_match('/^Pages:\s+(\d+)\s*$/mi', $salida, $matchPaginas) === 1
            ? (int) $matchPaginas[1]
            : 0;
        $tamano = preg_match('/^Page size:\s+(.+)$/mi', $salida, $matchTamano) === 1
            ? trim($matchTamano[1])
            : '';

        return ['paginas' => $paginas, 'tamano_pagina' => $tamano];
    }

    /** @param list<string> $comando */
    private function ejecutar(array $comando): void
    {
        $process = new Process($comando);
        $process->setEnv($this->entorno());
        $process->setTimeout(45);
        $process->mustRun();
    }

    private function binario(string $config, string $nombre, string $respaldo): string
    {
        $configurado = config('firma-electronica.'.$config);

        return is_string($configurado) && $configurado !== ''
            ? $configurado
            : (new ExecutableFinder)->find($nombre, $respaldo);
    }

    /** @return array<string, string> */
    private function entorno(): array
    {
        $home = function_exists('posix_getpwuid') && function_exists('posix_getuid')
            ? (string) (posix_getpwuid(posix_getuid())['dir'] ?? '/tmp')
            : (string) (getenv('USERPROFILE') ?: sys_get_temp_dir());

        return ['LANG' => 'C', 'HOME' => $home];
    }

    private function capturar(string $salida, string $campo): ?string
    {
        return preg_match('/^\\s*-?\\s*'.preg_quote($campo, '/').':\\s*(.+)$/mi', $salida, $match)
            ? trim($match[1])
            : null;
    }

    /** @return list<string> */
    private function separarFirmas(string $salida): array
    {
        $partes = preg_split('/(?=^\s*Signature #\d+:)/m', $salida) ?: [];

        return array_values(array_filter(
            $partes,
            static fn (string $parte): bool => preg_match('/^\s*Signature #\d+:/', $parte) === 1,
        ));
    }

    private function fallo(string $mensaje): DetalleValidacionFirma
    {
        return new DetalleValidacionFirma(
            ResultadoValidacionFirma::NoVerificado,
            false,
            false,
            false,
            false,
            false,
            error: $mensaje,
        );
    }
}
