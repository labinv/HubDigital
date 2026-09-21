<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models\EntidadDepositanteEloquentModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DescargarActaEntregaController
{
    public function __invoke(string $objeto, AlmacenamientoDepositos $almacenamiento): StreamedResponse
    {
        $ruta = $this->decodificar($objeto);
        $this->asegurarEntidadVigente($ruta);

        try {
            $stream = $almacenamiento->readStream($ruta);
        } catch (\Throwable) {
            abort(404);
        }

        return response()->streamDownload(function () use ($stream): void {
            try {
                stream_copy_to_stream($stream, fopen('php://output', 'wb'));
            } finally {
                fclose($stream);
            }
        }, basename($ruta), ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function urlParaRuta(string $ruta): string
    {
        self::validarRuta($ruta);
        $objeto = rtrim(strtr(base64_encode($ruta), '+/', '-_'), '=');

        return route('api.api.v1.taxonomia.entidades-depositantes.actas.descargar', ['objeto' => $objeto]);
    }

    private function decodificar(string $objeto): string
    {
        $normalizado = strtr($objeto, '-_', '+/');
        $ruta = base64_decode($normalizado.str_repeat('=', (4 - strlen($normalizado) % 4) % 4), true);
        if ($ruta === false) {
            abort(404);
        }
        self::validarRuta($ruta);

        return $ruta;
    }

    private static function validarRuta(string $ruta): void
    {
        if (preg_match(
            '#^inventario/actas/acta_entrega_[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}\\.txt$#Di',
            $ruta,
        ) !== 1) {
            abort(404);
        }
    }

    /**
     * La URL no concede acceso: además del rol y ability del grupo de ruta,
     * el acta debe pertenecer a una entidad que siga existiendo en la colección.
     * No hay aún una tabla de asignación de curador por entidad; el alcance
     * vigente del dominio para estos recursos es el rol CURADOR.
     */
    private function asegurarEntidadVigente(string $ruta): void
    {
        preg_match('#^inventario/actas/acta_entrega_([0-9a-f-]{36})_#Di', $ruta, $coincidencia);
        $entidadId = $coincidencia[1] ?? null;

        if ($entidadId === null || ! EntidadDepositanteEloquentModel::query()->whereKey($entidadId)->exists()) {
            abort(404);
        }
    }
}
