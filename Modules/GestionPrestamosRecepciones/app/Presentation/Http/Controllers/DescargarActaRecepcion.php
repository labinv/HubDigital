<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfActaRecepcion;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Entrega el acta final al depositante y la version por firmar al curador. */
final class DescargarActaRecepcion
{
    public function __invoke(
        string $id,
        ConsultarDetalleRecepcionHandler $handler,
        GeneradorPdfActaRecepcion $generadorPdf,
        AlmacenamientoDepositos $almacenamiento,
    ): Response {
        $recepcion = $handler->handle(new ConsultarDetalleRecepcionInput($id));
        abort_if($recepcion === null, 404);
        abort_unless($recepcion->actaEmitida, 404);

        $user = auth()->user();
        $esCurador = $user?->esCurador() ?? false;
        $esDueno = $recepcion->investigadorId === (string) $user?->id;
        abort_unless($esCurador || $esDueno, 403);

        $filename = 'Acta-recepcion-'.$recepcion->numeroSolicitud.'.pdf';
        if ($recepcion->actaFirmada && $recepcion->actaFirmadaRuta !== null
            && $almacenamiento->existe($recepcion->actaFirmadaRuta)) {
            return response($almacenamiento->obtener($recepcion->actaFirmadaRuta), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // El depositante no debe recibir una version final aun no firmada.
        abort_if($esDueno, 404);

        return response($generadorPdf->generar($recepcion), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
