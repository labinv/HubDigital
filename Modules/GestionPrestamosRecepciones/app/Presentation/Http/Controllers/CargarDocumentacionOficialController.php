<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\CargarDocumentacionOficial\CargarDocumentacionOficialInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Requests\CargarDocumentacionOficialRequest;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Resources\CargarDocumentacionOficialResource;

/**
 * Controlador para cargar la documentación oficial de una solicitud de depósito.
 */
final class CargarDocumentacionOficialController
{
    public function __construct(
        private readonly CargarDocumentacionOficialHandler $handler,
        private readonly AlmacenamientoDepositos $almacenamiento,
    ) {}

    /**
     * Maneja la petición HTTP para cargar documentos oficiales.
     */
    public function __invoke(CargarDocumentacionOficialRequest $request, string $id): JsonResponse
    {
        $documentosLocales = [];
        $documentosAlmacenados = [];

        try {
            foreach ($request->file('documentos', []) as $nombre => $archivo) {
                $rutaTemporal = $archivo->getRealPath();
                if ($rutaTemporal === false) {
                    throw new \RuntimeException('No se pudo acceder al archivo temporal cargado.');
                }

                $documentosLocales[$nombre] = $rutaTemporal;
                $documentosAlmacenados[$nombre] = $this->almacenamiento->guardarArchivo(
                    $archivo,
                    'depositos/'.$id.'/documentos-api',
                );
            }

            $output = ($this->handler)(new CargarDocumentacionOficialInput(
                solicitudId: $id,
                documentos: $documentosLocales,
                documentosAlmacenados: $documentosAlmacenados,
            ));
        } catch (\Throwable $error) {
            foreach ($documentosAlmacenados as $ruta) {
                try {
                    $this->almacenamiento->eliminar($ruta);
                } catch (\Throwable $errorLimpieza) {
                    report($errorLimpieza);
                }
            }

            throw $error;
        }

        return CargarDocumentacionOficialResource::make($output)->response();
    }
}
