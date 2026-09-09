<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\MatrizEspeciesEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Tests\TestCase;

uses(TestCase::class);

test('rehidrata la matriz asociada al reanudar una corrección desde un paso anterior', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitudId = (string) Str::uuid();
    $matrizId = (string) Str::uuid();
    $numero = sprintf('MEPN-INV-DEP-%05d', random_int(70000, 98999));

    SolicitudDepositoEloquentModel::query()->create([
        'id' => $solicitudId,
        'numero' => $numero,
        'investigador_id' => (string) $depositante->id,
        'tipo_tramite' => 'Depósito',
        'estado' => 'Requiere Corrección',
        'paso_actual' => 4,
        'documentos_adjuntos' => [],
        'documentos_cargados' => [],
        'datos_faltantes' => [],
        'datos_ingresados_manualmente' => [],
    ]);

    MatrizEspeciesEloquentModel::query()->create([
        'id' => $matrizId,
        'solicitud_id' => $solicitudId,
        'tipo_tramite' => 'Depósito',
        'estado' => 'Validada Técnicamente',
        'campos_dwc_presentes' => ['scientificName' => true],
        'identificacion_original_conservada' => false,
    ]);

    SolicitudDepositoEloquentModel::query()
        ->whereKey($solicitudId)
        ->update(['matriz_id' => $matrizId]);

    $this->actingAs($depositante);
    $component = app(RegistroSolicitudDeposito::class);
    $component->mount($solicitudId);

    expect($component->paso)->toBe(4)
        ->and($component->matrizId)->toBe($matrizId)
        ->and($component->matrizCargada)->toBeTrue();
});
