<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionInput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Presentation\Support\GeneradorPdfActaRecepcion;

#[Layout('layouts.app', params: ['title' => 'Acta final de recepción'])]
final class GestionActaRecepcion extends Component
{
    public string $id;

    public function mount(string $id): void
    {
        $this->id = $id;
    }

    public function generar(
        GenerarActaRecepcionHandler $handler,
        ConsultarDetalleRecepcionHandler $consultar,
        GeneradorPdfActaRecepcion $generadorPdf,
        AlmacenamientoDepositos $almacenamiento,
    ): void
    {
        $resultado = ($handler)(new GenerarActaRecepcionInput(
            solicitudId: $this->id,
            curadorId: (string) auth()->id(),
        ));
        $recepcion = $consultar->handle(new ConsultarDetalleRecepcionInput($this->id));
        abort_if($recepcion === null || $resultado->ruta === '', 409, 'No fue posible preparar el original oficial del acta.');
        $this->materializarOriginal($recepcion, $resultado->ruta, $generadorPdf, $almacenamiento, false);
        $this->dispatch('toast', message: 'Acta final generada. Ya puede revisarla y firmarla.');
    }

    public function reemitirOriginal(
        ConsultarDetalleRecepcionHandler $consultar,
        GeneradorPdfActaRecepcion $generadorPdf,
        AlmacenamientoDepositos $almacenamiento,
    ): void {
        $recepcion = $consultar->handle(new ConsultarDetalleRecepcionInput($this->id));
        abort_if($recepcion === null || ! $recepcion->actaEmitida || $recepcion->actaFirmada || $recepcion->actaRuta === null, 409);
        abort_if($almacenamiento->existe($recepcion->actaRuta), 409, 'El original oficial todavía está disponible.');

        $this->materializarOriginal($recepcion, $recepcion->actaRuta, $generadorPdf, $almacenamiento, true);
        $this->dispatch('toast', message: 'Se emitió una nueva versión del original. Revísela completa antes de firmar.');
    }

    private function materializarOriginal(
        object $recepcion,
        string $ruta,
        GeneradorPdfActaRecepcion $generadorPdf,
        AlmacenamientoDepositos $almacenamiento,
        bool $reemitida,
    ): void {
        if ($almacenamiento->existe($ruta)) {
            return;
        }

        $contenido = $generadorPdf->generar($recepcion);
        $almacenamiento->guardarContenido($ruta, $contenido, 'application/pdf');

        $lote = RecepcionLoteEloquentModel::query()
            ->where('solicitud_deposito_id', $this->id)
            ->firstOrFail();
        $lote->forceFill([
            'acta_original_sha256' => hash('sha256', $contenido),
            'acta_original_version' => $reemitida ? max(1, (int) $lote->acta_original_version + 1) : 1,
            'acta_original_materializada_en' => now(),
        ])->save();
    }

    public function render(
        ConsultarDetalleRecepcionHandler $consultar,
        UsuarioNombrePort $usuarios,
        AlmacenamientoDepositos $almacenamiento,
    ): View
    {
        $recepcion = $consultar->handle(new ConsultarDetalleRecepcionInput($this->id));
        abort_if($recepcion === null, 404);

        return view('gestionprestamosrecepciones::curador.gestion-acta-recepcion', [
            'recepcion' => $recepcion,
            'depositante' => $usuarios->obtenerNombre($recepcion->investigadorId),
            'receptor' => $recepcion->recibidoPor !== null ? $usuarios->obtenerNombre($recepcion->recibidoPor) : null,
            'originalDisponible' => $recepcion->actaRuta !== null && $almacenamiento->existe($recepcion->actaRuta),
        ]);
    }
}
