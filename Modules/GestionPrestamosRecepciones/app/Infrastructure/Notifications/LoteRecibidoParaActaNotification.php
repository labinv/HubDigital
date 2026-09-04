<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerta accionable para que curaduría genere y firme el acta final. */
final class LoteRecibidoParaActaNotification extends Notification
{
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly ?string $tipoTramite,
        public readonly ?string $nombreReceptor,
        public readonly bool $conObservaciones,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Acción requerida: generar y firmar acta'.($this->numero ? ' — '.$this->numero : ''))
            ->view('gestionprestamosrecepciones::mails.lote-recibido-para-acta', [
                'numero' => $this->numero,
                'tipoTramite' => $this->tipoTramite,
                'nombreReceptor' => $this->nombreReceptor,
                'conObservaciones' => $this->conObservaciones,
                'nombreCurador' => $notifiable->name ?? null,
                'url' => route('prestamos.curador.deposito.acta', $this->solicitudId),
            ]);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $numero = $this->numero ? ' '.$this->numero : '';

        return [
            'tipo' => 'lote_recibido_acta_pendiente',
            'solicitudId' => $this->solicitudId,
            'numero' => $this->numero,
            'mensaje' => 'Lote'.$numero.' recibido y constatado'.($this->conObservaciones ? ' con observaciones' : '').'. Genera y firma el acta final.',
            'url' => route('prestamos.curador.deposito.acta', $this->solicitudId),
            'icono' => 'bell-alert',
            'prioridad' => 'alta',
            'accion' => 'Generar y firmar acta',
        ];
    }
}
