<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Documentos;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\GestionPrestamosRecepciones\Application\Ports\GeneradorActaRecepcionPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionOutput;

final class DomPdfGeneradorActaRecepcionAdapter implements GeneradorActaRecepcionPort
{
    public function __construct(private readonly UsuarioNombrePort $usuarios) {}

    public function generar(ConsultarDetalleRecepcionOutput $recepcion): string
    {
        $depositante = $this->usuarios->obtenerDatosDepositante($recepcion->investigadorId);
        $curador = $recepcion->actaGeneradaPor !== null ? $this->usuarios->obtenerNombre($recepcion->actaGeneradaPor) : null;
        $receptor = $recepcion->recibidoPor !== null ? $this->usuarios->obtenerNombre($recepcion->recibidoPor) : null;
        $fechaBase = $recepcion->actaGeneradaEn ?? $recepcion->verificadoEn ?? now()->toDateTimeImmutable();
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $fecha = sprintf('%d de %s de %d', (int) $fechaBase->format('j'), $meses[(int) $fechaBase->format('n') - 1], (int) $fechaBase->format('Y'));
        $perfil = 'acta-recepcion:curador:v1';
        $ruta = str_replace([':', '_'], ['/', '-'], $perfil);

        return Pdf::loadView('gestionprestamosrecepciones::pdf.acta-recepcion', [
            'recepcion' => $recepcion,
            'depositante' => $depositante,
            'investigador' => $depositante?->nombre ?? $recepcion->investigadorId,
            'curador' => $curador,
            'receptor' => $receptor,
            'fecha' => $fecha,
            'perfilFirma' => ['perfil' => $perfil, 'rol' => 'curador', 'bloque' => 'https://firmas.hubdigital.invalid/bloques/'.$ruta, 'zona' => 'https://firmas.hubdigital.invalid/zonas/'.$ruta],
        ])->output();
    }
}
