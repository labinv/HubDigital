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
        'nro_permiso_recoleccion' => 'MAATE-QA-2026-001',
        'nro_permiso_movilizacion' => 'GUIA-QA-2026-001',
        'localidad' => 'Quito, Pichincha',
        'provincia_origen' => 'Pichincha',
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

test('rehidrata los valores derivados de la matriz al reanudar directamente en el paso cinco', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitudId = (string) Str::uuid();

    SolicitudDepositoEloquentModel::query()->create([
        'id' => $solicitudId,
        'numero' => sprintf('MEPN-INV-DEP-%05d', random_int(70000, 98999)),
        'investigador_id' => (string) $depositante->id,
        'tipo_tramite' => 'Depósito',
        'estado' => 'En Borrador',
        'paso_actual' => 5,
        'documentos_adjuntos' => [],
        'documentos_cargados' => [],
        'datos_faltantes' => [],
        'datos_ingresados_manualmente' => [],
        'nro_permiso_recoleccion' => 'MAATE-QA-2026-001',
        'nro_permiso_movilizacion' => 'GUIA-QA-2026-001',
        'localidad' => 'Quito, Pichincha',
        'provincia_origen' => 'Pichincha',
    ]);

    $this->actingAs($depositante);
    $component = app(RegistroSolicitudDeposito::class);
    $component->mount($solicitudId);

    expect($component->paso)->toBe(5)
        ->and($component->registroNativo['identifiedBy'])->toBe($depositante->name)
        ->and($component->registroNativo['recordedBy'])->toBe($depositante->name)
        ->and($component->registroNativo['researchPermit'])->toBe('MAATE-QA-2026-001')
        ->and($component->registroNativo['transportPermit'])->toBe('GUIA-QA-2026-001')
        ->and($component->registroNativo['verbatimLocality'])->toBe('Quito, Pichincha')
        ->and($component->registroNativo['stateProvince'])->toBe('Pichincha');
});
