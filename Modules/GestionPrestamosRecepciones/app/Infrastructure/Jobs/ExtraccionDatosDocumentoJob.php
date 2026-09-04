<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Exceptions\SolicitudNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Infrastructure\Exceptions\ModeloIANoDisponibleException;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/**
 * Trabajo en segundo plano para la extracción y validación de datos de documentos.
 *
 * Procesa de forma asíncrona una lista de documentos PDF cargados por el investigador,
 * extrayendo información mediante IA, verificando firmas electrónicas y finalmente
 * integrando estos datos en la solicitud de depósito correspondiente.
 */
final class ExtraccionDatosDocumentoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  string  $solicitudId  ID de la solicitud de depósito.
     * @param  array<string, string>  $documentos  Mapeo de [nombre_documento => ruta_storage].
     */
    public function __construct(
        private readonly string $solicitudId,
        private readonly array $documentos,
    ) {}

    /**
     * Ejecuta el procesamiento de los documentos.
     */
    public function handle(
        ExtraccionDatosDocumentoPort $extraccion,
        ValidacionFirmaElectronicaPort $validadorFirma,
        SolicitudDepositoRepositoryInterface $repo,
        TransactionManagerPort $transactionManager,
        EventPublisherPort $eventPublisher,
        AnalizadorDocumentoAmbiental $analizadorDocumento,
        AlmacenamientoDepositos $almacenamiento,
    ): void {
        SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
            ->update(['extraccion_estado' => 'procesando', 'documentos_procesados' => '[]']);

        try {
            $acumulado = [
                'nroPermisoRecoleccion' => null,
                'nroPermisoMovilizacion' => null,
                'grupoAnimal' => null,
                'provinciaOrigen' => null,
                'localidad' => null,
                'origenDonacion' => null,
                'nombreInvestigador' => null,
                'nroIndividuos' => null,
                'nroMorfoespecies' => null,
                'nroLotes' => null,
            ];

            $procesados = [];
            $metadatosExtraccion = [
                'motor' => 'local',
                'requiere_revision_humana' => true,
                'confirmacion_humana' => ['estado' => 'pendiente'],
                'documentos' => [],
                'campos' => [],
            ];
            $documentosAnalizados = [];
            $modelSolicitud = SolicitudDepositoEloquentModel::find($this->solicitudId);
            $nombresOriginales = $modelSolicitud?->nombres_archivos_originales ?? [];

            foreach ($this->documentos as $nombre => $ruta) {
                $parcial = $extraccion->extraerDatos([$nombre => $ruta]);

                foreach ([
                    'nroPermisoRecoleccion' => $parcial->nroPermisoRecoleccion,
                    'nroPermisoMovilizacion' => $parcial->nroPermisoMovilizacion,
                    'grupoAnimal' => $parcial->grupoAnimal,
                    'provinciaOrigen' => $parcial->provinciaOrigen,
                    'localidad' => $parcial->localidad,
                    'origenDonacion' => $parcial->origenDonacion,
                    'nombreInvestigador' => $parcial->nombreInvestigador,
                    'nroIndividuos' => $parcial->nroIndividuos,
                    'nroMorfoespecies' => $parcial->nroMorfoespecies,
                    'nroLotes' => $parcial->nroLotes,
                ] as $campo => $valor) {
                    if ($acumulado[$campo] === null && $valor !== null) {
                        $acumulado[$campo] = $valor;
                    }
                }

                $detalleDocumento = $parcial->metadatosExtraccion['documentos'][$nombre] ?? [];
                $metadatosExtraccion['documentos'][$nombre] = $detalleDocumento;
                $tipoEsperado = $analizadorDocumento->tipoEsperadoParaNombre($nombre);
                $analisis = is_array($detalleDocumento['analisis'] ?? null)
                    ? $detalleDocumento['analisis']
                    : [];
                if ($tipoEsperado !== null) {
                    $documentosAnalizados[$tipoEsperado] = $analisis;
                    $this->persistirDocumentoRegulatorio(
                        nombre: $nombre,
                        nombreOriginal: $nombresOriginales[$nombre] ?? null,
                        ruta: $ruta,
                        tipoEsperado: $tipoEsperado,
                        detalleDocumento: $detalleDocumento,
                        analisis: $analisis,
                        almacenamiento: $almacenamiento,
                    );
                }
                foreach (($parcial->metadatosExtraccion['campos'] ?? []) as $campo => $detalle) {
                    if (! isset($metadatosExtraccion['campos'][$campo])) {
                        $metadatosExtraccion['campos'][$campo] = $detalle;
                    }
                }

                $procesados[] = $nombre;
                $affected = SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                    ->update(['documentos_procesados' => json_encode($procesados)]);
                Log::debug('ExtraccionDatosDocumentoJob: progreso', [
                    'solicitudId' => $this->solicitudId,
                    'procesados' => $procesados,
                    'affected_rows' => $affected,
                    'db_value' => SolicitudDepositoEloquentModel::find($this->solicitudId)?->documentos_procesados,
                ]);
            }

            $validacionContenido = $analizadorDocumento->validarExpediente($documentosAnalizados);
            $metadatosExtraccion['validacion_contenido'] = $validacionContenido;

            // Los códigos de muestra son el vínculo entre la guía y cada fila de la
            // matriz. Se conservan sin fabricar una identificación taxonómica.
            $registrosSugeridos = [];
            foreach ($metadatosExtraccion['documentos'] as $detalle) {
                $analisis = $detalle['analisis'] ?? [];
                foreach (($analisis['codigos_muestra'] ?? []) as $codigo) {
                    $registrosSugeridos[$codigo] = [
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
            $metadatosExtraccion['registros_sugeridos'] = array_values($registrosSugeridos);

            // Validar firmas electrónicas de cada documento.
            $firmas = [];
            foreach ($this->documentos as $nombre => $ruta) {
                $copia = $almacenamiento->copiaLocal($ruta);
                try {
                    $estadoFirma = $validadorFirma->verificarFirma($copia->ruta())->value;
                } finally {
                    $copia->limpiar();
                }
                $firmas[$nombre] = $estadoFirma;
                $metadatosExtraccion['documentos'][$nombre]['firma_electronica'] = $estadoFirma;

                $tipoEsperado = $analizadorDocumento->tipoEsperadoParaNombre($nombre);
                if ($tipoEsperado !== null) {
                    DB::table('recepciones.documentos_regulatorios')
                        ->where('solicitud_id', $this->solicitudId)
                        ->where('tipo_esperado', $tipoEsperado)
                        ->update([
                            'firma_estado' => $estadoFirma,
                            'firma_verificada_en' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }

            Log::debug('ExtraccionDatosDocumentoJob: firmas electrónicas', [
                'solicitudId' => $this->solicitudId,
                'firmas' => $firmas,
            ]);

            $datosIntegrados = new DatosIntegradosDocumento(
                nroPermisoRecoleccion: $acumulado['nroPermisoRecoleccion'],
                nroPermisoMovilizacion: $acumulado['nroPermisoMovilizacion'],
                grupoAnimal: $acumulado['grupoAnimal'],
                provinciaOrigen: $acumulado['provinciaOrigen'],
                localidad: $acumulado['localidad'],
                origenDonacion: $acumulado['origenDonacion'],
                nombreInvestigador: $acumulado['nombreInvestigador'],
                nroIndividuos: $acumulado['nroIndividuos'],
                nroMorfoespecies: $acumulado['nroMorfoespecies'],
                nroLotes: $acumulado['nroLotes'],
                metadatosExtraccion: $metadatosExtraccion,
            );

            $id = SolicitudDepositoId::from($this->solicitudId);
            $solicitud = $repo->buscarPorId($id);

            if ($solicitud === null) {
                throw SolicitudNoEncontradaException::conId($this->solicitudId);
            }

            $solicitud->integrarDatosDeDocumentos(
                datos: $datosIntegrados,
                nombresDocumentos: array_keys($this->documentos),
            );

            $transactionManager->executeTransactional(function () use ($solicitud, $repo, $eventPublisher): void {
                $repo->guardar($solicitud);
                foreach ($solicitud->pullEvents() as $event) {
                    $eventPublisher->publish($event);
                }
            });

            // Persistir firmas después de la integración de dominio para evitar
            // inconsistencia si la transacción anterior falla.
            SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                ->update([
                    'firmas_electronicas' => json_encode($firmas),
                    'extraccion_metadatos' => json_encode($metadatosExtraccion),
                    'extraccion_estado' => 'completada',
                ]);
        } catch (ModeloIANoDisponibleException $e) {
            Log::warning('ExtraccionDatosDocumentoJob: modelo de IA no disponible', [
                'solicitudId' => $this->solicitudId,
                'error' => $e->getMessage(),
            ]);

            SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                ->update(['extraccion_estado' => 'error_modelo']);
        } catch (\Throwable $e) {
            Log::error('ExtraccionDatosDocumentoJob: error al procesar documentos', [
                'solicitudId' => $this->solicitudId,
                'error' => $e->getMessage(),
            ]);

            SolicitudDepositoEloquentModel::where('id', $this->solicitudId)
                ->update(['extraccion_estado' => 'fallida']);
        }
    }

    /** @param array<string, mixed> $detalleDocumento @param array<string, mixed> $analisis */
    private function persistirDocumentoRegulatorio(
        string $nombre,
        ?string $nombreOriginal,
        string $ruta,
        string $tipoEsperado,
        array $detalleDocumento,
        array $analisis,
        AlmacenamientoDepositos $almacenamiento,
    ): void {
        $ahora = now();
        $contenido = array_intersect_key($analisis, array_flip([
            'numero_documento', 'numero_autorizacion', 'titular', 'organizacion', 'ruc',
            'proyecto', 'emitido_en', 'valido_desde', 'valido_hasta', 'origen', 'destino',
            'codigos_muestra', 'grupos_biologicos', 'numero_individuos', 'numero_morfoespecies',
            'numero_lotes', 'texto_sha256', 'firma_declarada',
            'margen_clasificacion', 'requiere_confirmacion_humana', 'evidencias_campos',
        ]));

        DB::table('recepciones.documentos_regulatorios')->upsert([[
            'id' => (string) Str::uuid(),
            'solicitud_id' => $this->solicitudId,
            'tipo_esperado' => $tipoEsperado,
            'tipo_detectado' => $analisis['tipo_detectado'] ?? AnalizadorDocumentoAmbiental::DESCONOCIDO,
            'nombre_original' => $nombreOriginal ?? $nombre,
            'ruta' => $ruta,
            'sha256' => $almacenamiento->sha256($ruta),
            'motor_ocr' => $detalleDocumento['motor'] ?? null,
            'confianza' => $analisis['confianza'] ?? 0,
            'numero_documento' => $analisis['numero_documento'] ?? null,
            'numero_autorizacion_relacionada' => $analisis['numero_autorizacion'] ?? null,
            'titular' => $analisis['titular'] ?? null,
            'organizacion' => $analisis['organizacion'] ?? null,
            'ruc' => $analisis['ruc'] ?? null,
            'proyecto' => $analisis['proyecto'] ?? null,
            'emitido_en' => $analisis['emitido_en'] ?? null,
            'valido_desde' => $analisis['valido_desde'] ?? null,
            'valido_hasta' => $analisis['valido_hasta'] ?? null,
            'estado_validacion' => $analisis['estado'] ?? 'rechazado',
            'contenido_extraido' => json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'indicadores' => json_encode($analisis['puntajes'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'errores' => json_encode($analisis['errores'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'advertencias' => json_encode($analisis['advertencias'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]], ['solicitud_id', 'tipo_esperado'], [
            'tipo_detectado', 'nombre_original', 'ruta', 'sha256', 'motor_ocr', 'confianza',
            'numero_documento', 'numero_autorizacion_relacionada', 'titular', 'organizacion',
            'ruc', 'proyecto', 'emitido_en', 'valido_desde', 'valido_hasta',
            'estado_validacion', 'contenido_extraido', 'indicadores', 'errores', 'advertencias', 'updated_at',
        ]);
    }

}
