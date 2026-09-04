<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

test('el administrador ve cantidades estados y tendencia de depósitos', function (): void {
    $administrador = User::factory()->administrador()->create();

    foreach ([
        EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value,
        EstadoSolicitudDeposito::AprobadaDocumentalmente->value,
    ] as $indice => $estado) {
        SolicitudDepositoEloquentModel::query()->create([
            'id' => (string) Str::uuid(),
            'numero' => 'DEP-DASH-'.($indice + 1),
            'investigador_id' => (string) $administrador->id,
            'tipo_tramite' => 'Depósito',
            'estado' => $estado,
            'documentos_adjuntos' => [],
            'datos_faltantes' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($administrador)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Panel del curador')
        ->assertSee('Tendencia de solicitudes')
        ->assertSee('Estado de los depósitos')
        ->assertSee('Por revisar')
        ->assertSee('Aprobadas');
});
