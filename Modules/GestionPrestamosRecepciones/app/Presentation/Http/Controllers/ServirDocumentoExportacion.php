<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/**
 * Controlador para servir el documento de exportación de un acta de préstamo.
 */
final class ServirDocumentoExportacion
{
    public function __invoke(
        string $id,
        ConsultarDocumentoActaHandler $handler,
        AlmacenamientoDepositos $almacenamiento,
    ): Response
    {
        $user = auth()->user();

        $documento = $handler->handle(new ConsultarDocumentoActaInput(
            actaId: $id,
            usuarioId: (string) $user?->id,
            esCurador: $user?->esCurador() ?? false,
        ));

        if (! $documento->existe) {
            abort(404);
        }

        if (! $documento->autorizado) {
            abort(403);
        }

        if (! $documento->documentoExportacionRuta || ! $almacenamiento->existe($documento->documentoExportacionRuta)) {
            abort(404);
        }

        return response($almacenamiento->obtener($documento->documentoExportacionRuta), 200, [
            'Content-Type' => $almacenamiento->mimeType($documento->documentoExportacionRuta),
            'Content-Disposition' => 'inline; filename="documento-exportacion.pdf"',
        ]);
    }
}
