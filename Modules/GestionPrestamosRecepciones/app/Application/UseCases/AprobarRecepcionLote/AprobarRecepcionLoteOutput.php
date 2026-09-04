<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote;

/** Output compatible de recepción: el acta queda en falso hasta que actúe curaduría. */
final readonly class AprobarRecepcionLoteOutput
{
    public function __construct(
        public string $estado,
        public bool $actaDigitalRecepcionEmitida,
        public string $estadoColeccion,
        public bool $notificacionInvestigadorEnviada,
    ) {}

    public static function fromPrimitives(
        string $estado,
        bool $actaDigitalRecepcionEmitida,
        string $estadoColeccion,
        bool $notificacionInvestigadorEnviada,
    ): self {
        return new self(
            estado: $estado,
            actaDigitalRecepcionEmitida: $actaDigitalRecepcionEmitida,
            estadoColeccion: $estadoColeccion,
            notificacionInvestigadorEnviada: $notificacionInvestigadorEnviada,
        );
    }
}
