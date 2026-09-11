<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    config()->set('deposit-storage.driver', 'local');
    config()->set('deposit-storage.require_remote', false);
});

function solicitudOperacionDocumental(string $numero, string $ruta, string $contenido): SolicitudDepositoEloquentModel
{
    $usuario = User::factory()->depositante()->create();
    Storage::disk('local')->put($ruta, $contenido);

    return SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => $numero,
        'investigador_id' => (string) $usuario->id,
        'tipo_tramite' => 'Deposito',
        'estado' => 'Borrador',
        'documentos_adjuntos' => [['ruta' => $ruta, 'sha256' => hash('sha256', $contenido)]],
        'documentos_cargados' => ['permiso' => $ruta],
        'datos_faltantes' => [],
    ]);
}

test('concilia por expediente, detecta alteracion y limita huerfanos al prefijo indicado', function (): void {
    solicitudOperacionDocumental('MEPN-INV-DEP-99001', 'depositos/qa-ops/expediente/original.pdf', '%PDF-original');
    Storage::disk('local')->put('depositos/otra-area/legitimo.pdf', '%PDF-legitimo');
    Storage::disk('local')->put('depositos/qa-ops/huerfano.pdf', '%PDF-huerfano');

    $codigo = Artisan::call('depositos:conciliar-documentos', ['--expediente' => 'MEPN-INV-DEP-99001', '--prefijo' => 'depositos/qa-ops']);
    $resultado = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($codigo)->toBe(2)
        ->and($resultado['resumen'])->toMatchArray(['referencias' => 1, 'correctas' => 1, 'huerfanos' => 1])
        ->and($resultado['huerfanos'][0]['ruta'])->toBe('depositos/qa-ops/huerfano.pdf');

    Storage::disk('local')->put('depositos/qa-ops/expediente/original.pdf', '%PDF-alterado');
    Artisan::call('depositos:conciliar-documentos', ['--expediente' => 'MEPN-INV-DEP-99001']);
    $alterado = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($alterado['resultados'][0]['estado'])->toBe('ALTERADO');
});

test('respaldo reanudable verifica sha y restauracion rechaza manifiesto incompleto o destino no vacio', function (): void {
    solicitudOperacionDocumental('MEPN-INV-DEP-99002', 'depositos/qa-ops/dos/original-v1.pdf', '%PDF-respaldo');
    $manifiesto = storage_path('framework/testing/respaldo-operativo.json');

    $codigo = Artisan::call('depositos:respaldar-documentos', [
        '--id' => 'qa-ops-99002', '--prefijo-destino' => 'respaldos-depositos/qa-ops-99002',
        '--expediente' => 'MEPN-INV-DEP-99002', '--salida-manifiesto' => $manifiesto,
    ]);
    expect($codigo)->toBe(0);
    $datos = json_decode((string) file_get_contents($manifiesto), true, 512, JSON_THROW_ON_ERROR);
    expect($datos['estado'])->toBe('COMPLETO')->and($datos['objetos'])->toHaveCount(1);

    $destino = storage_path('framework/testing/restaurado-'.Str::uuid());
    expect(Artisan::call('depositos:restaurar-documentos', ['--manifiesto' => $manifiesto, '--directorio-destino' => $destino]))->toBe(0)
        ->and(hash_file('sha256', $destino.'/depositos/qa-ops/dos/original-v1.pdf'))->toBe(hash('sha256', '%PDF-respaldo'));
    expect(Artisan::call('depositos:restaurar-documentos', ['--manifiesto' => $manifiesto, '--directorio-destino' => $destino]))->toBe(4);

    $datos['estado'] = 'INCOMPLETO';
    file_put_contents($manifiesto, json_encode($datos, JSON_THROW_ON_ERROR));
    expect(Artisan::call('depositos:restaurar-documentos', ['--manifiesto' => $manifiesto, '--directorio-destino' => storage_path('framework/testing/otro-'.Str::uuid())]))->toBe(4);
});

test('rechaza una segunda ejecucion mientras el bloqueo de respaldo esta vigente', function (): void {
    $lock = Cache::lock('depositos:respaldo-documental', 30);
    expect($lock->get())->toBeTrue();
    try {
        $codigo = Artisan::call('depositos:respaldar-documentos', [
            '--id' => 'qa-concurrente',
            '--prefijo-destino' => 'respaldos-depositos/qa-concurrente',
            '--salida-manifiesto' => storage_path('framework/testing/concurrente.json'),
        ]);
        expect($codigo)->toBe(4)
            ->and(Artisan::output())->toContain('Ya existe un respaldo');
    } finally {
        $lock->release();
    }
});
