<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

/**
 * Clasifica y estructura documentos ambientales ecuatorianos por su contenido.
 *
 * El nombre del archivo no participa en la decisión. El clasificador combina
 * señales de estructura, vocabulario regulatorio, fechas, códigos y relaciones
 * entre documentos. Tesseract aporta el modelo OCR preentrenado; esta capa aplica
 * reglas de negocio deterministas y auditables sobre el texto reconocido.
 */
final class AnalizadorDocumentoAmbiental
{
    public const AUTORIZACION_RECOLECCION = 'autorizacion_recoleccion';

    public const GUIA_MOVILIZACION = 'guia_movilizacion';

    public const DESCONOCIDO = 'desconocido';

    /** @return array<string, mixed> */
    public function analizar(string $texto): array
    {
        $texto = $this->normalizarEspacios($texto);
        $seccionMuestras = $this->seccionMuestras($texto);
        $detallesPuntaje = [
            self::AUTORIZACION_RECOLECCION => $this->puntaje($texto, [
                ['/autorizaci[oó]n\s+de\s+recolecci[oó]n\s+de\s+vida\s+silvestre/iu', 0.46, 'titulo_autorizacion'],
                ['/otorga\s+la\s+autorizaci[oó]n/iu', 0.14, 'acto_otorgamiento'],
                ['/grupo\s+biol[oó]gico\s+a\s+estudiar/iu', 0.10, 'equipo_tecnico'],
                ['/oficio\s+nro\.?/iu', 0.10, 'numero_oficio'],
                ['/solicitar\s+oportunamente\s+la\s+gu[ií]a\s+de\s+movilizaci[oó]n/iu', 0.08, 'obligacion_movilizacion'],
                ['/directora?\s+(?:t[eé]cnica?|zonal)/iu', 0.06, 'autoridad_zonal'],
                ['/documento\s+firmado\s+electr[oó]nicamente/iu', 0.06, 'leyenda_firma'],
            ]),
            self::GUIA_MOVILIZACION => $this->puntaje($texto, [
                ['/gu[ií]a\s+de\s+movilizaci[oó]n\s+de\s+espec[ií]menes/iu', 0.48, 'titulo_guia'],
                ['/fecha\s+de\s+movilizaci[oó]n/iu', 0.12, 'fecha_movilizacion'],
                ['/v[aá]lido\s+hasta/iu', 0.10, 'vigencia'],
                ['/datos\s+de\s+las\s+muestras/iu', 0.10, 'tabla_muestras'],
                ['/responsable\s+de\s+la\s+movilizaci[oó]n/iu', 0.08, 'responsable_movilizacion'],
                ['/placa\s*:/iu', 0.06, 'vehiculo'],
                ['/tipo\s+de\s+transporte\s*:/iu', 0.06, 'transporte'],
            ]),
        ];

        $puntajes = array_map(
            static fn (array $detalle): float => (float) $detalle['puntaje'],
            $detallesPuntaje,
        );
        arsort($puntajes);
        $tipo = (string) array_key_first($puntajes);
        $confianza = (float) reset($puntajes);
        $segundo = (float) (array_values($puntajes)[1] ?? 0.0);
        if ($confianza < 0.55 || ($confianza - $segundo) < 0.12) {
            $tipo = self::DESCONOCIDO;
        }

        $numeroDocumento = match ($tipo) {
            self::GUIA_MOVILIZACION => $this->primero($texto, [
                '/gu[ií]a\s+de\s+movilizaci[oó]n[\s\S]{0,160}?(?:N(?:ro|o)?\.?|N[º°])\s*([A-Z0-9][A-Z0-9.\/_-]{7,})/iu',
                '/(?:N(?:ro|o)?\.?|N[º°])\s*([0-9]{2,4}-[0-9]{4}-(?:MAE|MAATE)[A-Z0-9.\/_-]*)/iu',
            ]),
            self::AUTORIZACION_RECOLECCION => $this->primero($texto, [
                '/oficio\s+nro\.?\s*([A-Z0-9][A-Z0-9.\/_-]{7,})/iu',
            ]),
            default => null,
        };

        $numeroAutorizacion = $this->normalizarCodigoPresentacion($this->primero($texto, [
            '/autorizaci[oó]n\s+de\s+recolecci[oó]n(?:\s+de\s+vida\s+silvestre)?[\s\S]{0,120}?([0-9](?:\s*[0-9]){1,3}\s*-\s*[0-9]{4}(?:\s+|\s*-\s*)[A-Z]{1,12}(?:\s*[\/-]+\s*[A-Z0-9]{1,12}){1,10})/iu',
            '/autorizaci[oó]n\s+de\s+recolecci[oó]n\s+de\s+vida\s+silvestre\s*(?:N(?:ro|o)?\.?|N[.º°o])?\s*([0-9][A-Z0-9.\/_-]{7,})/iu',
            '/autorizaci[oó]n\s+de\s+recolecci[oó]n[^\r\n]{0,80}(?:N(?:ro|o)?\.?|N[º°])\s*([0-9][A-Z0-9.\/_-]{7,})/iu',
        ]));

        $emitidoEn = $this->fechaDespuesDe($texto, [
            'Fecha de emisión',
            'Tena',
            'Quito',
            'Loja',
            'Machala',
        ]);
        $validoDesde = $this->fechaDespuesDe($texto, ['Fecha de movilización', 'Fecha de traslado']);
        $validoHasta = $this->fechaDespuesDe($texto, ['Válido hasta', 'Valido hasta']);

        $titular = $this->primero($texto, [
            '/autoriza\s+a\s*:\s*([^,\r\n]{5,140})/iu',
            '/se[ñn]or\s+([\p{L}][\p{L}\s.-]{5,120})\s+gerente\s+general/iu',
        ]);
        $organizacion = $this->primero($texto, [
            '/representante\s+legal\s+de\s+([^,\r\n]{3,140})/iu',
            '/consultora\s+ambiental\s+([A-Z0-9 ._-]{3,100})\s+mediante/iu',
            '/auspicio\s+de\s+([A-Z0-9 ._-]{3,100})/iu',
        ]);
        $proyecto = $this->primero($texto, [
            '/proyecto(?:\s+denominado)?\s*[“"]([^”"]{8,300})[”"]/iu',
            '/proyecto\s*:\s*([^\r\n]{8,300})/iu',
            '/estudio\s*:\s*([^\r\n]{8,300})/iu',
        ]);

        $ruc = $this->primero($texto, ['/\bRUC\s*([0-9]{13})\b/iu']);
        $origen = $this->primero($texto, ['/desde\s+(?:el\s+)?(?:sector\s+)?(.{4,220}?)\s*,?\s*hasta\s+/isu']);
        $destino = $this->primero($texto, ['/hasta\s+(?:el\s+)?(.{4,220}?)(?:\.|\r?\nDATOS)/isu']);
        $codigosMuestra = $this->codigosMuestra($seccionMuestras);
        $gruposBiologicos = $this->gruposBiologicos($texto);
        $numeroIndividuos = $this->numeroIndividuos($seccionMuestras !== '' ? $seccionMuestras : $texto);
        $numeroMorfoespecies = $this->enteroDespuesDe($texto, ['N.º morfoespecies', 'Número de morfoespecies', 'Total morfoespecies']);
        $numeroLotes = $this->numeroLotes($seccionMuestras !== '' ? $seccionMuestras : $texto);

        $camposDetectados = [
            'numero_documento' => $numeroDocumento,
            'numero_autorizacion' => $numeroAutorizacion,
            'titular' => $titular,
            'organizacion' => $organizacion,
            'ruc' => $ruc,
            'proyecto' => $proyecto,
            'emitido_en' => $emitidoEn,
            'valido_desde' => $validoDesde,
            'valido_hasta' => $validoHasta,
            'origen' => $origen,
            'destino' => $destino,
        ];
        $evidenciasCampos = [];
        foreach ($camposDetectados as $campo => $valor) {
            if (is_string($valor) && $valor !== '') {
                $evidencia = $this->evidenciaValor($texto, $valor);
                if ($evidencia !== null) {
                    $evidenciasCampos[$campo] = $evidencia;
                }
            }
        }

        $indicadores = $detallesPuntaje;
        $errores = [];
        $advertencias = [];
        if ($tipo === self::DESCONOCIDO) {
            $errores[] = 'El contenido no corresponde inequívocamente a una autorización de recolección ni a una guía de movilización.';
        }
        if ($numeroDocumento === null) {
            $errores[] = 'No se pudo identificar el número oficial del documento.';
        }
        if ($tipo !== self::DESCONOCIDO && $numeroAutorizacion === null) {
            $errores[] = 'No se pudo identificar el número de la autorización de recolección relacionada.';
        }
        if (preg_match('/ministerio\s+del\s+ambiente/iu', $texto) !== 1) {
            $advertencias[] = 'No se reconoció claramente al Ministerio del Ambiente como entidad emisora.';
        }
        if (preg_match('/firmado\s+electr[oó]nicamente/iu', $texto) !== 1) {
            $advertencias[] = 'No se encontró una leyenda de firma electrónica; la firma criptográfica se valida por separado.';
        }
        if ($tipo === self::GUIA_MOVILIZACION && ($validoDesde === null || $validoHasta === null)) {
            $advertencias[] = 'No fue posible reconstruir todo el período autorizado de movilización.';
        }
        if ($validoDesde !== null && $validoHasta !== null && $validoDesde > $validoHasta) {
            $errores[] = 'La fecha de movilización es posterior a la fecha límite de validez.';
        }

        return [
            'tipo_detectado' => $tipo,
            'confianza' => round($confianza, 4),
            'margen_clasificacion' => round($confianza - $segundo, 4),
            'requiere_confirmacion_humana' => true,
            'puntajes' => $indicadores,
            'numero_documento' => $numeroDocumento,
            'numero_autorizacion' => $numeroAutorizacion,
            'titular' => $titular,
            'organizacion' => $organizacion,
            'ruc' => $ruc,
            'proyecto' => $proyecto,
            'emitido_en' => $emitidoEn,
            'valido_desde' => $validoDesde,
            'valido_hasta' => $validoHasta,
            'origen' => $origen,
            'destino' => $destino,
            'codigos_muestra' => $codigosMuestra,
            'grupos_biologicos' => $gruposBiologicos,
            'numero_individuos' => $numeroIndividuos,
            'numero_morfoespecies' => $numeroMorfoespecies,
            'numero_lotes' => $numeroLotes,
            'evidencias_campos' => $evidenciasCampos,
            'texto_sha256' => hash('sha256', $texto),
            'firma_declarada' => preg_match('/firmado\s+electr[oó]nicamente/iu', $texto) === 1,
            'errores' => $errores,
            'advertencias' => $advertencias,
            'estado' => $errores !== [] ? 'rechazado' : ($advertencias !== [] ? 'revision' : 'valido'),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $documentos Clave = tipo esperado.
     * @return array{estado: string, errores: list<string>, advertencias: list<string>}
     */
    public function validarExpediente(array $documentos): array
    {
        $errores = [];
        $advertencias = [];

        $contieneDocumentoRegulatorio = isset($documentos[self::AUTORIZACION_RECOLECCION])
            || isset($documentos[self::GUIA_MOVILIZACION]);
        if ($contieneDocumentoRegulatorio) {
            foreach ([self::AUTORIZACION_RECOLECCION, self::GUIA_MOVILIZACION] as $requerido) {
                if (! isset($documentos[$requerido])) {
                    $errores[] = 'Falta la '.$this->etiqueta($requerido).' requerida para contrastar el expediente.';
                }
            }
        }

        foreach ($documentos as $esperado => $documento) {
            $detectado = $documento['tipo_detectado'] ?? self::DESCONOCIDO;
            if ($detectado !== $esperado) {
                $errores[] = sprintf(
                    'El archivo cargado como %s fue identificado por su contenido como %s.',
                    $this->etiqueta($esperado),
                    $this->etiqueta((string) $detectado),
                );
            }
            foreach (($documento['errores'] ?? []) as $error) {
                $errores[] = $this->etiqueta($esperado).': '.$error;
            }
            foreach (($documento['advertencias'] ?? []) as $advertencia) {
                $advertencias[] = $this->etiqueta($esperado).': '.$advertencia;
            }
        }

        $autorizacion = $documentos[self::AUTORIZACION_RECOLECCION] ?? null;
        $guia = $documentos[self::GUIA_MOVILIZACION] ?? null;
        if (is_array($autorizacion) && is_array($guia)) {
            $codigoAutorizacion = $this->normalizarCodigo($autorizacion['numero_autorizacion'] ?? null);
            $codigoGuia = $this->normalizarCodigo($guia['numero_autorizacion'] ?? null);
            if ($codigoAutorizacion !== null && $codigoGuia !== null && $codigoAutorizacion !== $codigoGuia) {
                $errores[] = sprintf(
                    'La guía cita la autorización %s, pero el oficio aportado concede la %s.',
                    (string) $guia['numero_autorizacion'],
                    (string) $autorizacion['numero_autorizacion'],
                );
            }

            $organizacionAutorizacion = (string) ($autorizacion['organizacion'] ?? '');
            $organizacionGuia = (string) ($guia['organizacion'] ?? '');
            if ($organizacionAutorizacion !== '' && $organizacionGuia !== '' && ! $this->textosRelacionados($organizacionAutorizacion, $organizacionGuia, 0.45)) {
                $errores[] = 'La organización titular no coincide entre la autorización y la guía de movilización.';
            }

            $proyectoAutorizacion = (string) ($autorizacion['proyecto'] ?? '');
            $proyectoGuia = (string) ($guia['proyecto'] ?? '');
            if ($proyectoAutorizacion !== '' && $proyectoGuia !== '' && ! $this->textosRelacionados($proyectoAutorizacion, $proyectoGuia, 0.28)) {
                $errores[] = 'El proyecto descrito en la guía no coincide con el proyecto autorizado en el oficio.';
            }

            $emisionAutorizacion = $autorizacion['emitido_en'] ?? null;
            $movilizacion = $guia['valido_desde'] ?? null;
            if (is_string($emisionAutorizacion) && is_string($movilizacion) && $movilizacion < $emisionAutorizacion) {
                $errores[] = 'La movilización consta con una fecha anterior a la emisión de la autorización.';
            }
        }

        $errores = array_values(array_unique($errores));
        $advertencias = array_values(array_unique($advertencias));

        return [
            'estado' => $errores !== [] ? 'rechazado' : ($advertencias !== [] ? 'revision' : 'valido'),
            'errores' => $errores,
            'advertencias' => $advertencias,
        ];
    }

    public function tipoEsperadoParaNombre(string $nombre): ?string
    {
        return match ($nombre) {
            'Copia de la autorización de recolección (MAE)' => self::AUTORIZACION_RECOLECCION,
            'Copia del permiso de movilización' => self::GUIA_MOVILIZACION,
            default => null,
        };
    }

    /** @param list<array{0: string, 1: float, 2: string}> $reglas @return array{puntaje: float, señales: list<string>, evidencias: list<array{senal: string, fragmento: string}>} */
    private function puntaje(string $texto, array $reglas): array
    {
        $puntaje = 0.0;
        $senales = [];
        $evidencias = [];
        foreach ($reglas as [$patron, $peso, $senal]) {
            if (preg_match($patron, $texto, $coincidencia, PREG_OFFSET_CAPTURE) === 1) {
                $puntaje += $peso;
                $senales[] = $senal;
                $evidencias[] = [
                    'senal' => $senal,
                    'fragmento' => $this->fragmentoEnOffset($texto, (int) $coincidencia[0][1]),
                ];
            }
        }

        return ['puntaje' => min(1.0, $puntaje), 'señales' => $senales, 'evidencias' => $evidencias];
    }

    /** @param list<string> $patrones */
    private function primero(string $texto, array $patrones): ?string
    {
        foreach ($patrones as $patron) {
            if (preg_match($patron, $texto, $coincidencia) === 1) {
                $valor = trim(preg_replace('/\s+/u', ' ', (string) $coincidencia[1]) ?? (string) $coincidencia[1], " \t\n\r\0\x0B.,;:");

                return $valor !== '' ? $valor : null;
            }
        }

        return null;
    }

    /** @param list<string> $patrones @return list<string> */
    private function todos(string $texto, array $patrones): array
    {
        $valores = [];
        foreach ($patrones as $patron) {
            if (preg_match_all($patron, $texto, $coincidencias) > 0) {
                foreach ($coincidencias[1] as $valor) {
                    $valores[] = trim((string) $valor);
                }
            }
        }

        return array_values(array_unique(array_filter($valores)));
    }

    /** @return list<string> */
    private function gruposBiologicos(string $texto): array
    {
        $catalogo = [
            'Macroinvertebrados acuáticos', 'Coleópteros', 'Ephemeroptera', 'Coleoptera',
            'Trichoptera', 'Odonata', 'Anfibios', 'Arácnidos', 'Crustáceos', 'Moluscos',
            'Anélidos', 'Insectos', 'Flora',
        ];
        $hallados = [];
        foreach ($catalogo as $grupo) {
            if (preg_match('/\b'.preg_quote($grupo, '/').'\b/iu', $texto) === 1) {
                $hallados[] = $grupo;
            }
        }

        return array_values(array_unique($hallados));
    }

    /** @param list<string> $prefijos */
    private function fechaDespuesDe(string $texto, array $prefijos): ?string
    {
        foreach ($prefijos as $prefijo) {
            $patron = '/'.preg_quote($prefijo, '/').'[^\r\n0-9]{0,20}([0-3]?\d\s+de\s+[\p{L}]+\s+(?:de|del)\s+\d{4}|\d{1,2}[\/-]\d{1,2}[\/-]\d{4})/iu';
            if (preg_match($patron, $texto, $coincidencia) === 1) {
                return $this->normalizarFecha((string) $coincidencia[1]);
            }
        }

        return null;
    }

    private function normalizarFecha(string $fecha): ?string
    {
        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', trim($fecha), $partes) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $partes[3], (int) $partes[2], (int) $partes[1]);
        }
        if (preg_match('/(\d{1,2})\s+de\s+([\p{L}]+)\s+(?:de|del)\s+(\d{4})/iu', $fecha, $partes) !== 1) {
            return null;
        }
        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        ];
        $mes = $meses[mb_strtolower($partes[2])] ?? null;

        return $mes === null ? null : sprintf('%04d-%02d-%02d', (int) $partes[3], $mes, (int) $partes[1]);
    }

    /** @param list<string> $etiquetas */
    private function enteroDespuesDe(string $texto, array $etiquetas): ?int
    {
        foreach ($etiquetas as $etiqueta) {
            if (preg_match('/'.preg_quote($etiqueta, '/').'\s*[:#-]?\s*(\d{1,7})/iu', $texto, $coincidencia) === 1) {
                return (int) $coincidencia[1];
            }
        }

        return null;
    }

    private function numeroLotes(string $texto): ?int
    {
        $totalDeclarado = $this->enteroDespuesDe($texto, ['N.º lotes', 'Número de lotes', 'Total lotes']);
        if ($totalDeclarado !== null) {
            return $totalDeclarado;
        }
        if (preg_match_all('/\b(\d{1,6})\s+lotes?\b/iu', $texto, $coincidencias) < 1) {
            return null;
        }

        return array_sum(array_map('intval', $coincidencias[1]));
    }

    private function numeroIndividuos(string $texto): ?int
    {
        $totalDeclarado = $this->enteroDespuesDe($texto, ['N.º individuos', 'Número de individuos', 'Total individuos']);
        if ($totalDeclarado !== null) {
            return $totalDeclarado;
        }
        if (preg_match_all('/\b(\d{1,7})\s+individuos?\b/iu', $texto, $coincidencias) < 1) {
            return null;
        }

        return array_sum(array_map('intval', $coincidencias[1]));
    }

    private function seccionMuestras(string $texto): string
    {
        if (preg_match('/datos\s+de\s+las\s+muestras\s*([\s\S]{1,20000})/iu', $texto, $coincidencia) !== 1) {
            return '';
        }

        $seccion = (string) $coincidencia[1];
        $partes = preg_split(
            '/\r?\n\s*(?:observaciones|documento\s+firmado|firma(?:\s+electr[oó]nica)?|responsable\s+de\s+la\s+emisi[oó]n)\b/iu',
            $seccion,
            2,
        );

        return trim((string) ($partes[0] ?? ''));
    }

    /** @return list<string> */
    private function codigosMuestra(string $seccionMuestras): array
    {
        if ($seccionMuestras === '') {
            return [];
        }
        if (preg_match_all(
            '/\b([A-Z][A-Z0-9]{1,9}(?:-[A-Z0-9]{1,12}){1,4})\b/u',
            $seccionMuestras,
            $coincidencias,
            PREG_OFFSET_CAPTURE,
        ) < 1) {
            return [];
        }

        $excluidos = ['MAE', 'MAATE', 'RUC', 'SUIA'];
        $codigos = [];
        foreach ($coincidencias[1] as [$codigo, $offset]) {
            $prefijo = mb_strtolower(mb_strcut($seccionMuestras, max(0, (int) $offset - 40), 40, 'UTF-8'));
            if (str_contains($prefijo, 'placa') || in_array(mb_strtoupper((string) $codigo), $excluidos, true)) {
                continue;
            }
            $codigos[] = (string) $codigo;
        }

        return array_values(array_unique($codigos));
    }

    private function evidenciaValor(string $texto, string $valor): ?string
    {
        if (preg_match('/'.preg_quote($valor, '/').'/iu', $texto, $coincidencia, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $this->fragmentoEnOffset($texto, (int) $coincidencia[0][1]);
    }

    private function fragmentoEnOffset(string $texto, int $offset): string
    {
        $inicio = max(0, $offset - 80);
        $fragmento = mb_strcut($texto, $inicio, 240, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $fragmento) ?? $fragmento);
    }

    private function normalizarCodigoPresentacion(?string $codigo): ?string
    {
        if ($codigo === null) {
            return null;
        }

        $codigo = preg_replace('/\s*([\/-])\s*/u', '$1', $codigo) ?? $codigo;
        $codigo = preg_replace('/(?<=\d)\s+(?=\d)/u', '', $codigo) ?? $codigo;

        return trim(preg_replace('/\s+/u', ' ', $codigo) ?? $codigo);
    }

    private function normalizarEspacios(string $texto): string
    {
        return trim(preg_replace('/[ \t]+/u', ' ', str_replace("\0", '', $texto)) ?? $texto);
    }

    private function normalizarCodigo(mixed $codigo): ?string
    {
        if (! is_string($codigo) || trim($codigo) === '') {
            return null;
        }

        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($codigo)) ?: null;
    }

    private function textosRelacionados(string $a, string $b, float $umbral): bool
    {
        $tokensA = $this->tokens($a);
        $tokensB = $this->tokens($b);
        if ($tokensA === [] || $tokensB === []) {
            return false;
        }
        $interseccion = array_intersect($tokensA, $tokensB);
        $union = array_unique([...$tokensA, ...$tokensB]);

        return count($interseccion) / count($union) >= $umbral;
    }

    /** @return list<string> */
    private function tokens(string $texto): array
    {
        $texto = mb_strtolower($texto);
        $texto = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $texto) ?? $texto;
        $stop = ['de', 'del', 'la', 'el', 'para', 'y', 'en', 'los', 'las', 'proyecto', 's', 'a'];

        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', trim($texto)) ?: [],
            fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, $stop, true),
        )));
    }

    private function etiqueta(string $tipo): string
    {
        return match ($tipo) {
            self::AUTORIZACION_RECOLECCION => 'autorización de recolección',
            self::GUIA_MOVILIZACION => 'guía de movilización',
            default => 'documento no reconocido',
        };
    }
}
