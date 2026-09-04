<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionOutput;

/** Genera de forma determinista el acta oficial que luego firma el curador. */
final class GeneradorPdfActaRecepcion
{
    public function __construct(private readonly UsuarioNombrePort $usuarios) {}

    public function generar(ConsultarDetalleRecepcionOutput $recepcion): string
    {
        $depositante = $this->usuarios->obtenerDatosDepositante($recepcion->investigadorId);
        $curador = $recepcion->actaGeneradaPor !== null
            ? $this->usuarios->obtenerNombre($recepcion->actaGeneradaPor)
            : null;
        $receptor = $recepcion->recibidoPor !== null
            ? $this->usuarios->obtenerNombre($recepcion->recibidoPor)
            : null;
        $fechaBase = $recepcion->actaGeneradaEn ?? $recepcion->verificadoEn ?? now()->toDateTimeImmutable();
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
            'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $fecha = sprintf(
            '%d de %s de %d',
            (int) $fechaBase->format('j'),
            $meses[(int) $fechaBase->format('n') - 1],
            (int) $fechaBase->format('Y'),
        );

        return Pdf::loadView('gestionprestamosrecepciones::pdf.acta-recepcion', [
            'recepcion' => $recepcion,
            'depositante' => $depositante,
            'investigador' => $depositante?->nombre ?? $recepcion->investigadorId,
            'curador' => $curador,
            'receptor' => $receptor,
            'fecha' => $fecha,
        ])->output();
    }
}
