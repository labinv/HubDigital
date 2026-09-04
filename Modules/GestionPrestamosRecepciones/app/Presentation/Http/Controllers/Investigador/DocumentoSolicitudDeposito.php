<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Sirve el formulario oficial generado o su copia final firmada desde almacenamiento privado. */
final class DocumentoSolicitudDeposito
{
    public function __invoke(
        Request $request,
        string $id,
        GeneradorPdfSolicitudDeposito $generador,
        AlmacenamientoDepositos $almacenamiento,
    ): Response {
        $solicitud = SolicitudDepositoEloquentModel::findOrFail($id);
        $usuario = $request->user();
        $esDueno = (string) $solicitud->investigador_id === (string) $usuario->id;
        abort_unless($esDueno || $usuario->esCurador() || $usuario->esReceptor(), 403);

        $servirOriginal = $request->boolean('original');
        if (! $servirOriginal
            && $solicitud->solicitud_firmada_ruta !== null
            && $almacenamiento->existe($solicitud->solicitud_firmada_ruta)
        ) {
            $contenido = $almacenamiento->obtener($solicitud->solicitud_firmada_ruta);
        } else {
            abort_unless($esDueno || $usuario->esCurador(), 403);
            $contenido = $generador->generar($solicitud);
        }

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Solicitud-'.$solicitud->numero.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
