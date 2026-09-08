<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionOutput;

interface OriginalActaRecepcionPort
{
    /** @return array{referencia:string,ruta:string,sha256:string,version:int} */
    public function materializar(string $solicitudId, string $actorId, ConsultarDetalleRecepcionOutput $recepcion, bool $reemitir = false, ?int $versionEsperada = null): array;

    /** @return array{referencia:string,ruta:string,sha256:string,version:int,contenido:string} */
    public function obtenerVerificado(string $solicitudId): array;

    public function disponible(string $solicitudId): bool;

    public function versionActual(string $solicitudId): int;
}
