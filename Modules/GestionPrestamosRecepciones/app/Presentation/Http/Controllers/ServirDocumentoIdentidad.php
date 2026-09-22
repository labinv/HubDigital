<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDocumentoActa\ConsultarDocumentoActaInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/**
 * Controlador para servir el documento de identidad asociado a un acta de préstamo.
 */
final class ServirDocumentoIdentidad
{
    public function __invoke(
        string $id,
        ConsultarDocumentoActaHandler $handler,
        AlmacenamientoDepositos $almacenamiento,
    ): Response {
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

        if (! $documento->documentoIdentidadRuta || ! $almacenamiento->existe($documento->documentoIdentidadRuta)) {
            abort(404);
        }

        return response($almacenamiento->obtenerVerificado($documento->documentoIdentidadRuta, $documento->documentoIdentidadSha256), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="documento-identidad.pdf"',
        ]);
    }
}
