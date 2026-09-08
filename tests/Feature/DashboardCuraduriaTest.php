<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
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

test('el curador y el administrador ven solo las actas constatadas pendientes en su cola', function (): void {
    $pendiente = SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => 'DEP-PEND-1',
        'investigador_id' => (string) Str::uuid(),
        'tipo_tramite' => 'Depósito',
        'estado' => EstadoSolicitudDeposito::AprobadaDocumentalmente->value,
        'documentos_adjuntos' => [],
        'datos_faltantes' => [],
    ]);
    $firmada = SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => 'DEP-FIRM-1',
        'investigador_id' => (string) Str::uuid(),
        'tipo_tramite' => 'Depósito',
        'estado' => EstadoSolicitudDeposito::AprobadaDocumentalmente->value,
        'documentos_adjuntos' => [],
        'datos_faltantes' => [],
    ]);

    foreach ([[$pendiente, null], [$firmada, 'actas/firmada.pdf']] as [$solicitud, $actaFirmadaRuta]) {
        RecepcionLoteEloquentModel::query()->create([
            'id' => (string) Str::uuid(),
            'solicitud_deposito_id' => $solicitud->id,
            'codigo_qr' => 'QR-'.$solicitud->numero,
            'tipo_tramite' => 'Depósito',
            'estado' => 'Verificado Físicamente',
            'items_verificacion' => [],
            'observaciones' => [],
            'verificado_en' => now(),
            'acta_firmada_ruta' => $actaFirmadaRuta,
        ]);
    }

    foreach ([User::factory()->curador()->create(), User::factory()->administrador()->create()] as $usuario) {
        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('DEP-PEND-1')
            ->assertSee('Acta pendiente de firma')
            ->assertDontSee('DEP-FIRM-1');
    }
});
