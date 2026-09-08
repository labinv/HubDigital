<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionOutput;

interface GeneradorActaRecepcionPort
{
    public function generar(ConsultarDetalleRecepcionOutput $recepcion): string;
}
