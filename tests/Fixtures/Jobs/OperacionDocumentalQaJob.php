<?php

declare(strict_types=1);

namespace Tests\Fixtures\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use RuntimeException;

/** Fixture de cola para ensayos aislados de respaldo/restauracion. */
final class OperacionDocumentalQaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $notificationId,
        public readonly ?string $oldPath = null,
        public readonly ?string $newPath = null,
    ) {}

    public function handle(AlmacenamientoDepositos $storage): void
    {
        if ($this->newPath !== null && ! $storage->existe($this->newPath)) {
            throw new RuntimeException('La version nueva sintetica aun no esta disponible.');
        }
        if ($this->oldPath !== null) {
            $storage->eliminar($this->oldPath);
        }
        DB::table('notifications')->insertOrIgnore([
            'id' => $this->notificationId,
            'type' => 'QA\\OperacionDocumentalNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => '92000000-0000-4000-8000-000000000001',
            'data' => json_encode(['evento' => $this->newPath === null ? 'respaldo_restaurado' : 'reemplazo_documental', 'expediente' => 'MEPN-INV-DEP-99010']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
