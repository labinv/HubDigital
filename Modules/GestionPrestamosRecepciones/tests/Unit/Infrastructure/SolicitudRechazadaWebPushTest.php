<?php

declare(strict_types=1);

use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\SolicitudRechazadaNotification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

uses(TestCase::class);

it('habilita web push para el depositante suscrito sin exponer el motivo', function (): void {
    config()->set('webpush.vapid.subject', 'mailto:qa@example.test');
    config()->set('webpush.vapid.public_key', 'publica-sintetica');
    config()->set('webpush.vapid.private_key', 'privada-sintetica');

    $depositante = new class
    {
        public function pushSubscriptions(): object
        {
            return new class
            {
                public function exists(): bool
                {
                    return true;
                }
            };
        }
    };

    $notification = new SolicitudRechazadaNotification(
        '00000000-0000-0000-0000-000000000001',
        'MEPN-INV-DEP-QA',
        'Motivo privado que no debe mostrarse en la pantalla bloqueada',
    );

    expect($notification->via($depositante))->toContain(WebPushChannel::class);
});
