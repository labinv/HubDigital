<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Alerta accionable para que curaduría genere y firme el acta final. */
final class LoteRecibidoParaActaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** La cola solo recibe el aviso después de confirmar la constatación física. */
    public function __construct(
        public readonly string $solicitudId,
        public readonly ?string $numero,
        public readonly ?string $tipoTramite,
        public readonly ?string $nombreReceptor,
        public readonly bool $conObservaciones,
    ) {
        // Queueable ya define esta propiedad. Usar su API evita una colisión
        // de propiedades tipadas y conserva el despacho posterior al commit.
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if (filled(config('webpush.vapid.subject'))
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'))
            && method_exists($notifiable, 'pushSubscriptions')
            && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
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
            'eventoId' => $this->eventoId(),
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

    public function toWebPush(object $notifiable): WebPushMessage
    {
        // El contenido visible en la pantalla bloqueada es deliberadamente
        // genérico; el detalle sensible permanece tras autenticación.
        return (new WebPushMessage)
            ->title('Acción pendiente en HubDigital')
            ->body('Un depósito recibido requiere revisión curatorial y firma del acta.')
            ->icon('/images/hub-icon.png')
            ->badge('/images/hub-icon.png')
            ->tag($this->eventoId())
            ->data([
                'notificationId' => $this->eventoId(),
                'url' => route('prestamos.curador.deposito.acta', $this->solicitudId),
            ])
            ->options(['TTL' => 86400, 'urgency' => 'high']);
    }

    private function eventoId(): string
    {
        return 'deposito-acta-'.$this->solicitudId;
    }
}
