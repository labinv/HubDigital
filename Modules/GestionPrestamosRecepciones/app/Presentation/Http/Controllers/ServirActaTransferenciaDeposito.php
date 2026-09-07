<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\Response;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/**
 * Sirve, mediante streaming autenticado, el Acta de Transferencia de Dominio de una
 * donación aprobada.
 *
 * Acceso permitido solo al curador o al depositante dueño de la solicitud. Se entrega
 * `inline`; con `?descargar=1` se entrega como descarga.
 */
final class ServirActaTransferenciaDeposito
{
    public function __invoke(string $id, AlmacenamientoDepositos $almacenamiento): Response
    {
        $user = auth()->user();

        $deposito = SolicitudDepositoEloquentModel::find($id);
        abort_if($deposito === null, 404);

        $esCurador = $user?->esCurador() ?? false;
        $esDueno = (string) $deposito->investigador_id === (string) $user?->id;
        abort_unless($esCurador || $esDueno, 403);

        $ruta = $deposito->acta_transferencia_dominio['ruta'] ?? null;
        abort_if(! is_string($ruta) || trim($ruta) === '' || ! $almacenamiento->existe($ruta), 404);

        $disposicion = request()->boolean('descargar') ? 'attachment' : 'inline';

        return response($almacenamiento->obtener($ruta), 200, [
            'Content-Type' => $almacenamiento->mimeType($ruta),
            'Content-Disposition' => $disposicion.'; filename="acta-transferencia-'.$id.'.pdf"',
        ]);
    }
}
