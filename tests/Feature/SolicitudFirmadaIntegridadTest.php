<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Services\InvalidarFirmaSolicitud;

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('deposit-storage.driver', 'local');
    config()->set('deposit-storage.require_remote', false);
});

function solicitudFirmadaParaIntegridad(User $depositante): SolicitudDepositoEloquentModel
{
    $ruta = 'solicitudes-deposito/firmadas/'.Str::uuid().'-v1.pdf';
    $contenido = "%PDF-1.7\nsolicitud firmada vigente";
    Storage::disk('local')->put($ruta, $contenido);

    return SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => 'MEPN-INV-DEP-'.random_int(70000, 99999),
        'investigador_id' => (string) $depositante->id,
        'tipo_tramite' => 'Depósito',
        'estado' => 'Requiere Corrección',
        'documentos_adjuntos' => [],
        'datos_faltantes' => [],
        'solicitud_firmada_ruta' => $ruta,
        'solicitud_firmada_sha256' => hash('sha256', $contenido),
        'solicitud_firmada_en' => now(),
        'solicitud_firma_metadata' => ['resultado' => 'Firmado'],
        'solicitud_documento_version' => 1,
    ]);
}

test('un fallo al actualizar metadatos no elimina el documento firmado vigente', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitud = solicitudFirmadaParaIntegridad($depositante);
    $ruta = $solicitud->solicitud_firmada_ruta;

    $escucha = static function (SolicitudDepositoEloquentModel $modelo) use ($solicitud): void {
        if ((string) $modelo->id === (string) $solicitud->id && $modelo->solicitud_firmada_ruta === null) {
            throw new RuntimeException('Fallo de persistencia inyectado');
        }
    };
    SolicitudDepositoEloquentModel::saving($escucha);

    expect(fn () => app(InvalidarFirmaSolicitud::class)(
        (string) $solicitud->id,
        (string) $depositante->id,
    ))->toThrow(RuntimeException::class, 'Fallo de persistencia inyectado');

    SolicitudDepositoEloquentModel::flushEventListeners();
    $vigente = $solicitud->fresh();
    expect($vigente->solicitud_firmada_ruta)->toBe($ruta)
        ->and($vigente->solicitud_firmada_sha256)->toBe($solicitud->solicitud_firmada_sha256)
        ->and($vigente->solicitud_firmada_en)->not->toBeNull();
    Storage::disk('local')->assertExists($ruta);
});

test('la invalidación confirma metadatos antes de retirar la versión anterior', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitud = solicitudFirmadaParaIntegridad($depositante);
    $ruta = $solicitud->solicitud_firmada_ruta;

    expect(app(InvalidarFirmaSolicitud::class)(
        (string) $solicitud->id,
        (string) $depositante->id,
    ))->toBeTrue();

    $vigente = $solicitud->fresh();
    expect($vigente->solicitud_firmada_ruta)->toBeNull()
        ->and($vigente->solicitud_firmada_sha256)->toBeNull()
        ->and($vigente->solicitud_firmada_en)->toBeNull()
        ->and($vigente->solicitud_documento_version)->toBe(2);
    Storage::disk('local')->assertMissing($ruta);
});
