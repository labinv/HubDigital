<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaRecepcionFirmada\SubirActaRecepcionFirmadaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\SubirActaRecepcionFirmada\SubirActaRecepcionFirmadaInput;
use Modules\GestionPrestamosRecepciones\Application\Ports\OriginalActaRecepcionPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Recibe solo el PDF que el firmador local produjo; nunca recibe el P12 o su clave. */
final class FirmarActaRecepcion
{
    public function __invoke(
        Request $request,
        string $id,
        ConsultarDetalleRecepcionHandler $consultar,
        SubirActaRecepcionFirmadaHandler $guardarFirma,
        AlmacenamientoDepositos $almacenamiento,
        OriginalActaRecepcionPort $originales,
    ): JsonResponse {
        $request->validate([
            'pdf_firmado' => ['required', 'file', 'mimes:pdf', 'max:15360'],
            'original_referencia' => ['required', 'uuid'],
            'original_sha256' => ['required', 'string', 'size:64'],
        ]);

        $recepcion = $consultar->handle(new ConsultarDetalleRecepcionInput($id));
        abort_if($recepcion === null, 404);
        abort_unless($recepcion->actaEmitida, 409, 'Primero debe generarse el acta final.');
        abort_if($recepcion->actaFirmada, 409, 'El acta ya fue firmada y cerrada.');

        $originalTemporal = null;
        $rutaRelativa = null;
        try {
            $original = $originales->obtenerVerificado($id);
            if (! hash_equals($original['referencia'], (string) $request->input('original_referencia'))
                || ! hash_equals($original['sha256'], (string) $request->input('original_sha256'))) {
                abort(409, 'El original cambió antes de iniciar la firma. Actualice la pantalla y firme la versión vigente.');
            }
            $pdfOriginal = $original['contenido'];

            $originalTemporal = tempnam(sys_get_temp_dir(), 'hubdigital-acta-');
            if ($originalTemporal === false) {
                abort(500, 'No se pudo preparar el documento oficial para validar.');
            }
            @chmod($originalTemporal, 0600);
            if (file_put_contents($originalTemporal, $pdfOriginal, LOCK_EX) === false) {
                abort(500, 'No se pudo preparar el documento oficial para validar.');
            }

            // Un nombre no predecible evita que dos intentos concurrentes o un archivo
            // rechazado sobrescriban un acta previamente validada.
            $rutaRelativa = 'actas/recepcion-firmada/'.$id.'-'.Str::uuid().'.pdf';
            $archivo = $request->file('pdf_firmado');
            $rutaGuardada = $almacenamiento->guardarSubidoComo($archivo, $rutaRelativa);
            abort_unless($rutaGuardada === $rutaRelativa, 500, 'No se pudo guardar el acta firmada.');

            ($guardarFirma)(new SubirActaRecepcionFirmadaInput(
                solicitudId: $id,
                curadorId: (string) $request->user()->id,
                rutaRelativa: $rutaRelativa,
                rutaAbsoluta: $archivo->getRealPath(),
                rutaOriginalAbsoluta: $originalTemporal,
                referenciaOriginal: (string) $request->input('original_referencia'),
                sha256Original: (string) $request->input('original_sha256'),
            ));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\DomainException $e) {
            if ($rutaRelativa !== null) {
                $almacenamiento->eliminar($rutaRelativa);
            }

            return response()->json([
                'message' => 'La firma fue rechazada: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            if ($rutaRelativa !== null) {
                $almacenamiento->eliminar($rutaRelativa);
            }
            Log::error('No se pudo validar o persistir el acta firmada', [
                'solicitud_id' => $id,
                'curador_id' => (string) $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No fue posible validar y guardar el acta firmada.',
            ], 500);
        } finally {
            if (is_string($originalTemporal)) {
                @unlink($originalTemporal);
            }
        }

        return response()->json([
            'message' => 'Acta firmada y validada por HubDigital.',
        ]);
    }
}
