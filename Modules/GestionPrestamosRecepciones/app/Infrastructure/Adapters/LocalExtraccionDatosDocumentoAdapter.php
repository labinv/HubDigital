<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

/**
 * Extractor privado y gratuito: texto embebido + OCR Tesseract 5 (spa/eng).
 * Los resultados son propuestas trazables que el ciudadano debe confirmar.
 */
final class LocalExtraccionDatosDocumentoAdapter implements ExtraccionDatosDocumentoPort
{
    private AnalizadorDocumentoAmbiental $analizador;

    private AlmacenamientoDepositos $almacenamiento;

    /** @var list<string> */
    private const PROVINCIAS = [
        'Azuay', 'Bolivar', 'Canar', 'Carchi', 'Chimborazo', 'Cotopaxi', 'El Oro',
        'Esmeraldas', 'Galapagos', 'Guayas', 'Imbabura', 'Loja', 'Los Rios', 'Manabi',
        'Morona Santiago', 'Napo', 'Orellana', 'Pastaza', 'Pichincha', 'Santa Elena',
        'Santo Domingo de los Tsachilas', 'Sucumbios', 'Tungurahua', 'Zamora Chinchipe',
    ];

    public function __construct(
        ?AnalizadorDocumentoAmbiental $analizador = null,
        ?AlmacenamientoDepositos $almacenamiento = null,
    )
    {
        $this->analizador = $analizador ?? new AnalizadorDocumentoAmbiental;
        $this->almacenamiento = $almacenamiento ?? app(AlmacenamientoDepositos::class);
    }

    public function extraerDatos(array $documentos): DatosIntegradosDocumento
    {
        $valores = [];
        $metadata = ['motor' => 'pdftotext+tesseract-5', 'requiere_revision_humana' => true, 'documentos' => [], 'campos' => []];

        foreach ($documentos as $nombre => $ruta) {
            [$texto, $motor] = $this->leerTexto($ruta);
            $analisis = $this->analizador->analizar($texto);
            $tipoEsperado = $this->analizador->tipoEsperadoParaNombre($nombre);
            $contenidoCompatible = $tipoEsperado === null
                || ($analisis['tipo_detectado'] ?? null) === $tipoEsperado;
            $metadata['documentos'][$nombre] = [
                'motor' => $motor,
                'caracteres' => mb_strlen($texto),
                'texto_sha256' => hash('sha256', $texto),
                'procesado_localmente' => true,
                'modelo_ocr' => str_starts_with($motor, 'tesseract') ? 'Tesseract 5 spa+eng' : null,
                'contenido_compatible_con_casilla' => $contenidoCompatible,
                'analisis' => $analisis,
            ];

            $estructurados = [
                'nroPermisoRecoleccion' => $analisis['numero_autorizacion'] ?? null,
                'nroPermisoMovilizacion' => ($analisis['tipo_detectado'] ?? null) === AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION
                    ? ($analisis['numero_documento'] ?? null)
                    : null,
                'grupoAnimal' => ($analisis['grupos_biologicos'] ?? []) !== []
                    ? implode(', ', $analisis['grupos_biologicos'])
                    : null,
                'provinciaOrigen' => null,
                'localidad' => $analisis['origen'] ?? null,
                'origenDonacion' => null,
                'nombreInvestigador' => $analisis['titular'] ?? null,
                'nroIndividuos' => isset($analisis['numero_individuos']) ? (string) $analisis['numero_individuos'] : null,
                'nroMorfoespecies' => isset($analisis['numero_morfoespecies']) ? (string) $analisis['numero_morfoespecies'] : null,
                'nroLotes' => isset($analisis['numero_lotes']) ? (string) $analisis['numero_lotes'] : null,
            ];

            foreach ($estructurados as $campo => $valor) {
                if (! $contenidoCompatible) {
                    continue;
                }
                if (($valores[$campo] ?? null) === null && is_string($valor) && trim($valor) !== '') {
                    $campoAnalisis = match ($campo) {
                        'nroPermisoRecoleccion' => 'numero_autorizacion',
                        'nroPermisoMovilizacion' => 'numero_documento',
                        'localidad' => 'origen',
                        'nombreInvestigador' => 'titular',
                        default => null,
                    };
                    $valores[$campo] = trim($valor);
                    $metadata['campos'][$campo] = [
                        'confianza' => $analisis['confianza'] ?? 0.0,
                        'fuente' => $nombre,
                        'motor' => $motor,
                        'metodo' => 'clasificador_ambiental',
                        'evidencia' => $campoAnalisis !== null
                            ? ($analisis['evidencias_campos'][$campoAnalisis] ?? null)
                            : null,
                        'requiere_confirmacion_humana' => true,
                    ];
                }
            }

            foreach ($tipoEsperado === null ? $this->extraerCampos($texto) : [] as $campo => $hallazgo) {
                if (($valores[$campo] ?? null) === null && $hallazgo['valor'] !== null) {
                    $valores[$campo] = $hallazgo['valor'];
                    $metadata['campos'][$campo] = [
                        'confianza' => $hallazgo['confianza'],
                        'fuente' => $nombre,
                        'motor' => $motor,
                        'metodo' => 'patron_auditable',
                        'evidencia' => $hallazgo['evidencia'],
                        'requiere_confirmacion_humana' => true,
                    ];
                }
            }

            foreach (($analisis['codigos_muestra'] ?? []) as $codigo) {
                $metadata['registros_sugeridos'][] = [
                    'recordNumber' => $codigo,
                    'researchPermit' => $analisis['numero_autorizacion'] ?? null,
                    'transportPermit' => ($analisis['tipo_detectado'] ?? null) === AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION
                        ? ($analisis['numero_documento'] ?? null)
                        : null,
                    'verbatimLocality' => $analisis['origen'] ?? null,
                    'gruposBiologicos' => $analisis['grupos_biologicos'] ?? [],
                ];
            }
        }

        $metadata['registros_sugeridos'] = array_values(array_reduce(
            $metadata['registros_sugeridos'] ?? [],
            static function (array $unicos, array $registro): array {
                $codigo = (string) ($registro['recordNumber'] ?? '');
                if ($codigo !== '') {
                    $unicos[$codigo] = array_replace($unicos[$codigo] ?? [], $registro);
                }

                return $unicos;
            },
            [],
        ));

        return new DatosIntegradosDocumento(
            nroPermisoRecoleccion: $valores['nroPermisoRecoleccion'] ?? null,
            nroPermisoMovilizacion: $valores['nroPermisoMovilizacion'] ?? null,
            grupoAnimal: $valores['grupoAnimal'] ?? null,
            provinciaOrigen: $valores['provinciaOrigen'] ?? null,
            localidad: $valores['localidad'] ?? null,
            origenDonacion: $valores['origenDonacion'] ?? null,
            nombreInvestigador: $valores['nombreInvestigador'] ?? null,
            nroIndividuos: $valores['nroIndividuos'] ?? null,
            nroMorfoespecies: $valores['nroMorfoespecies'] ?? null,
            nroLotes: $valores['nroLotes'] ?? null,
            metadatosExtraccion: $metadata,
        );
    }

    /** @return array{string, string} */
    private function leerTexto(string $ruta): array
    {
        $copia = $this->almacenamiento->copiaLocal($ruta);
        $archivo = $copia->ruta();
        if (! is_file($archivo)) {
            return ['', 'archivo-no-encontrado'];
        }

        try {
            $this->validarPdfSeguro($archivo);
            $texto = $this->ejecutar(new Process(['pdftotext', '-layout', '-nopgbrk', $archivo, '-']));
            if (mb_strlen(trim($texto)) < config('document-extraction.minimum_text_length', 80)) {
                $ocr = $this->aplicarOcr($archivo);
                if (mb_strlen(trim($ocr)) > mb_strlen(trim($texto))) {
                    return [$ocr, 'tesseract-5-spa-eng'];
                }
            }

            if (trim($texto) === '') {
                try {
                    $texto = (new Parser)->parseFile($archivo)->getText();
                } catch (\Throwable $e) {
                    Log::warning('No se pudo leer PDF para autocompletado', ['error' => $e->getMessage()]);
                }
            }

            return [trim($texto), 'pdftotext'];
        } finally {
            $copia->limpiar();
        }
    }

    /**
     * Rechaza documentos patológicos antes de entregarlos a Poppler/Tesseract.
     * El peso del archivo no limita el tamaño descomprimido de una página PDF.
     */
    private function validarPdfSeguro(string $archivo): void
    {
        $maxPaginas = max(1, (int) config('document-extraction.ocr_max_pages', 25));
        $maxPoints = max(842, (int) config('document-extraction.max_page_points', 1440));
        $dpi = max(72, min(300, (int) config('document-extraction.ocr_dpi', 200)));
        $maxPixeles = max(10_000_000, (int) config('document-extraction.max_render_pixels', 120_000_000));

        $proceso = new Process([
            'pdfinfo', '-box', '-f', '1', '-l', (string) ($maxPaginas + 1), $archivo,
        ]);
        $proceso->setTimeout(15);
        $proceso->run();
        if (! $proceso->isSuccessful()) {
            throw new \RuntimeException('El PDF no superó la validación técnica previa.');
        }

        $salida = $proceso->getOutput();
        $paginas = preg_match('/^Pages:\s+(\d+)\s*$/mi', $salida, $matchPaginas) === 1
            ? (int) $matchPaginas[1]
            : 0;
        if ($paginas < 1 || $paginas > $maxPaginas) {
            throw new \RuntimeException("El PDF debe tener entre 1 y {$maxPaginas} páginas.");
        }

        preg_match_all(
            '/^(?:Page(?:\s+\d+)?\s+)?size:\s*([0-9.]+)\s+x\s+([0-9.]+)\s+pts/mi',
            $salida,
            $tamanos,
            PREG_SET_ORDER,
        );
        if ($tamanos === []) {
            throw new \RuntimeException('No se pudo validar la geometría de las páginas del PDF.');
        }

        $maxPixelesPagina = 0.0;
        foreach ($tamanos as $tamano) {
            $ancho = (float) $tamano[1];
            $alto = (float) $tamano[2];
            if ($ancho <= 0 || $alto <= 0 || $ancho > $maxPoints || $alto > $maxPoints) {
                throw new \RuntimeException('El PDF contiene una página con dimensiones no permitidas.');
            }
            $maxPixelesPagina = max($maxPixelesPagina, ($ancho * $dpi / 72) * ($alto * $dpi / 72));
        }

        if (($maxPixelesPagina * $paginas) > $maxPixeles) {
            throw new \RuntimeException('El PDF excede el límite seguro de procesamiento gráfico.');
        }
    }

    private function aplicarOcr(string $archivo): string
    {
        $directorio = storage_path('app/private/tmp/ocr/'.Str::uuid());
        File::ensureDirectoryExists($directorio, 0700, true);
        $prefijo = $directorio.DIRECTORY_SEPARATOR.'pagina';

        try {
            $this->ejecutar(new Process([
                'pdftoppm', '-f', '1', '-l', (string) config('document-extraction.ocr_max_pages', 25),
                '-r', (string) config('document-extraction.ocr_dpi', 200), '-jpeg', $archivo, $prefijo,
            ], timeout: 180));

            $paginas = File::glob($prefijo.'-*.jpg');
            sort($paginas, SORT_NATURAL);
            $texto = [];
            foreach ($paginas as $pagina) {
                $texto[] = $this->ejecutar(new Process([
                    'tesseract', $pagina, 'stdout', '-l', (string) config('document-extraction.ocr_languages', 'spa+eng'), '--psm', '6',
                ], timeout: 90));
            }

            return trim(implode("\n", $texto));
        } finally {
            File::deleteDirectory($directorio);
        }
    }

    private function ejecutar(Process $process): string
    {
        try {
            $process->run();
            return $process->isSuccessful() ? $process->getOutput() : '';
        } catch (\Throwable $e) {
            Log::notice('Motor local de documentos no disponible', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /** @return array<string, array{valor: ?string, confianza: float, evidencia: ?string}> */
    private function extraerCampos(string $texto): array
    {
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;

        return [
            'nroPermisoRecoleccion' => $this->buscar($texto, [
                '/(?:autorizaci[oó]n|permiso)(?:\s+de)?\s+recolecci[oó]n[^\r\n:]{0,35}[:#\-]?\s*([A-Z0-9][A-Z0-9.\/_-]{5,})/iu',
                '/\b((?:MAATE|MAE)[A-Z0-9.\/_-]{5,})\b/iu',
            ]),
            'nroPermisoMovilizacion' => $this->buscar($texto, [
                '/(?:gu[ií]a|permiso)(?:\s+de)?\s+movilizaci[oó]n[^\r\n:]{0,35}[:#\-]?\s*([A-Z0-9][A-Z0-9.\/_-]{5,})/iu',
            ]),
            'grupoAnimal' => $this->buscar($texto, [
                '/(?:grupo(?:\s+animal)?|grupo\s+taxon[oó]mico)\s*[:\-]\s*([^\r\n]{3,90})/iu',
                '/\b(moluscos?|insectos?|crust[aá]ceos?|an[eé]lidos?|ar[aá]cnidos?)\b/iu',
            ]),
            'provinciaOrigen' => $this->buscarProvincia($texto),
            'localidad' => $this->buscar($texto, [
                '/(?:localidad|sitio\s+de\s+colecta)\s*[:\-]\s*([^\r\n]{3,120})/iu',
            ]),
            'origenDonacion' => $this->buscar($texto, [
                '/(?:origen|procedencia)(?:\s+de\s+(?:la\s+)?donaci[oó]n|\s+de\s+los\s+espec[ií]menes)?\s*[:\-]\s*([^\r\n]{3,180})/iu',
            ]),
            'nombreInvestigador' => $this->buscar($texto, [
                '/(?:titular|investigador(?:a)?|depositante|nombre(?:\s+completo)?)\s*[:\-]\s*([\p{L}][\p{L} .\'-]{4,100})/iu',
            ]),
        ];
    }

    /** @param list<string> $patrones @return array{valor: ?string, confianza: float, evidencia: ?string} */
    private function buscar(string $texto, array $patrones): array
    {
        foreach ($patrones as $indice => $patron) {
            if (preg_match($patron, $texto, $m, PREG_OFFSET_CAPTURE) === 1) {
                $capturado = (string) $m[1][0];
                $valor = trim(preg_replace('/\s+/u', ' ', $capturado) ?? $capturado, " \t\n\r\0\x0B.,;:");
                $inicio = max(0, (int) $m[0][1] - 60);
                $fragmento = mb_strcut($texto, $inicio, 220, 'UTF-8');

                return [
                    'valor' => $valor !== '' ? $valor : null,
                    'confianza' => $indice === 0 ? 0.9 : 0.72,
                    'evidencia' => trim(preg_replace('/\s+/u', ' ', $fragmento) ?? $fragmento),
                ];
            }
        }

        return ['valor' => null, 'confianza' => 0.0, 'evidencia' => null];
    }

    /** @return array{valor: ?string, confianza: float, evidencia: ?string} */
    private function buscarProvincia(string $texto): array
    {
        if (preg_match('/(?:provincia|administraci[oó]n\s+pol[ií]tica)\s*[:\-]\s*([^\r\n]{3,120})/iu', $texto, $coincidencia, PREG_OFFSET_CAPTURE) !== 1) {
            return ['valor' => null, 'confianza' => 0.0, 'evidencia' => null];
        }

        $capturado = (string) $coincidencia[1][0];
        $ascii = Str::lower(Str::ascii($capturado));
        foreach (self::PROVINCIAS as $provincia) {
            if (str_contains($ascii, Str::lower(Str::ascii($provincia)))) {
                $inicio = max(0, (int) $coincidencia[0][1] - 40);
                $fragmento = mb_strcut($texto, $inicio, 200, 'UTF-8');

                return [
                    'valor' => $provincia,
                    'confianza' => 0.9,
                    'evidencia' => trim(preg_replace('/\s+/u', ' ', $fragmento) ?? $fragmento),
                ];
            }
        }

        return ['valor' => null, 'confianza' => 0.0, 'evidencia' => null];
    }
}
