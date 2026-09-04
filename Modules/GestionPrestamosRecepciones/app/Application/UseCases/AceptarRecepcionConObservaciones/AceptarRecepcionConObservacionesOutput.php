<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AceptarRecepcionConObservaciones;

/** Output compatible: las observaciones aún no están en acta hasta que curaduría la genere. */
final readonly class AceptarRecepcionConObservacionesOutput
{
    public function __construct(
        public string $estado,
        public bool $observacionesRegistradasEnActa,
        public string $estadoColeccion,
        public bool $notificacionInvestigadorEnviada,
    ) {}

    public static function fromPrimitives(
        string $estado,
        bool $observacionesRegistradasEnActa,
        string $estadoColeccion,
        bool $notificacionInvestigadorEnviada,
    ): self {
        return new self(
            estado: $estado,
            observacionesRegistradasEnActa: $observacionesRegistradasEnActa,
            estadoColeccion: $estadoColeccion,
            notificacionInvestigadorEnviada: $notificacionInvestigadorEnviada,
        );
    }
}
