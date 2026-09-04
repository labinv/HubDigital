<?php

declare(strict_types=1);

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarDocumentalmenteSolicitud\AprobarDocumentalmenteSolicitudInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\EnviarSolicitudDeposito\EnviarSolicitudDepositoInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\GenerarActaRecepcion\GenerarActaRecepcionInput;
use Modules\GestionPrestamosRecepciones\Application\UseCases\AprobarRecepcionLote\AprobarRecepcionLoteOutput;
use Modules\GestionPrestamosRecepciones\Domain\Entities\MatrizEspecies;
use Modules\GestionPrestamosRecepciones\Domain\Entities\SolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\MatrizEspeciesRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudDepositoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemChecklistRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\NumeroSolicitudDeposito;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\LoteRecibidoParaActaNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Notifications\NuevaSolicitudPorRevisarNotification;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Tests\Infrastructure\Adapters\FakeEventPublisherAdapter;

test('el depósito completo persiste actores, documentos, taxonomía, recepción, alerta y acta firmada', function (): void {
    $this->skipUnlessFortifyFeature(Features::registration());

    Notification::fake();
    Storage::fake('local');
    config()->set('deposit-storage.driver', 'local');
    config()->set('deposit-storage.require_remote', false);
    config()->set('firma-electronica.exigir_certificado_confiable', true);

    app()->instance(EventPublisherPort::class, new FakeEventPublisherAdapter);
    app()->instance(ExtraccionDatosDocumentoPort::class, new class implements ExtraccionDatosDocumentoPort
    {
        public function extraerDatos(array $documentos): DatosIntegradosDocumento
        {
            expect($documentos)->toHaveCount(2);
            foreach ($documentos as $rutaTemporal) {
                expect(is_file($rutaTemporal))->toBeTrue();
            }

            return new DatosIntegradosDocumento(
                nroPermisoRecoleccion: 'MAATE-DZ8-2026-1691-OF',
                nroPermisoMovilizacion: 'GUIA-2026-000417',
                grupoAnimal: 'Insecta',
                provinciaOrigen: 'Pichincha',
                localidad: 'Reserva Geobotánica Pululahua',
                origenDonacion: null,
                nombreInvestigador: 'Ana Depositante Prueba',
                nroIndividuos: '12',
                nroMorfoespecies: '1',
                nroLotes: '1',
                metadatosExtraccion: [
                    'requiere_revision_humana' => false,
                    'confianza_global' => 0.99,
                ],
            );
        }
    });
    app()->instance(ValidacionFirmaElectronicaPort::class, new class implements ValidacionFirmaElectronicaPort
    {
        public function verificarFirma(string $rutaAbsoluta): ResultadoValidacionFirma
        {
            return ResultadoValidacionFirma::Firmado;
        }

        public function verificarFirmaDetallada(string $rutaFirmadaAbsoluta, string $rutaOriginalAbsoluta): DetalleValidacionFirma
        {
            expect(is_file($rutaFirmadaAbsoluta))->toBeTrue()
                ->and(is_file($rutaOriginalAbsoluta))->toBeTrue();

            return new DetalleValidacionFirma(
                resultado: ResultadoValidacionFirma::Firmado,
                integridadCriptografica: true,
                documentoCompletoFirmado: true,
                contenidoOficialCoincide: true,
                certificadoVigente: true,
                certificadoConfiable: true,
                certificado: [
                    'nombre' => 'Firmante de prueba',
                    'tipo_firma' => 'ETSI.CAdES.detached',
                ],
            );
        }
    });

    $this->post(route('register.store'), [
        'first_name' => 'Ana',
        'last_name' => 'Depositante Prueba',
        'email' => 'ana.depositante.e2e@example.test',
        'password' => 'Clave-Segura-2026!',
        'password_confirmation' => 'Clave-Segura-2026!',
        'rol' => RolUsuario::DEPOSITANTE->value,
        'cargo' => 'Consultora ambiental',
        'institucion' => 'Consultoría E2E',
    ])->assertSessionHasNoErrors();

    $depositante = User::query()->where('email', 'ana.depositante.e2e@example.test')->sole();
    $this->get(route('depositos.solicitud.crear'))
        ->assertRedirect(route('verification.notice'));

    $depositante->markEmailAsVerified();
    $this->post(route('logout'));
    $this->post(route('login.store'), [
        'email' => 'ANA.DEPOSITANTE.E2E@EXAMPLE.TEST',
        'password' => 'Clave-Segura-2026!',
    ])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($depositante);
    $this->get(route('depositos.solicitud.crear'))->assertOk();

    $receptor = User::factory()->receptor()->create([
        'email' => 'receptor.e2e@epn.edu.ec',
    ]);
    $curador = User::factory()->curador()->create([
        'email' => 'curador.e2e@epn.edu.ec',
    ]);

    $this->actingAs($depositante)
        ->get(route('prestamos.receptor.depositos'))
        ->assertForbidden();
    $this->actingAs($depositante)
        ->get(route('prestamos.curador.depositos'))
        ->assertForbidden();

    $solicitudes = app(SolicitudDepositoRepositoryInterface::class);
    $solicitud = SolicitudDeposito::crear(
        id: $solicitudes->nextIdentity(),
        numero: NumeroSolicitudDeposito::fromSecuencia(90001),
        investigadorId: (string) $depositante->id,
        tipoTramite: TipoTramite::Deposito->value,
    );
    $solicitudes->guardar($solicitud);

    $apiLogin = $this->postJson('/api/login', [
        'email' => $depositante->email,
        'password' => 'Clave-Segura-2026!',
    ])->assertOk();
    $token = (string) $apiLogin->json('token');
    expect($token)->not->toBeEmpty();

    $carga = $this->withToken($token)->post(
        route('solicitudes-deposito.documentacion-oficial', (string) $solicitud->id()),
        [
            'documentos' => [
                'Permiso de investigación' => UploadedFile::fake()->createWithContent(
                    'permiso-maate.pdf',
                    "%PDF-1.7\npermiso de prueba",
                ),
                'Guía de movilización' => UploadedFile::fake()->createWithContent(
                    'guia-movilizacion.pdf',
                    "%PDF-1.7\nguía de prueba",
                ),
            ],
        ],
        ['Accept' => 'application/json'],
    );
    $carga->assertOk()->assertJsonPath('data.estado', EstadoSolicitudDeposito::EnBorrador->value);

    $expediente = SolicitudDepositoEloquentModel::findOrFail((string) $solicitud->id());
    expect($expediente->nro_permiso_recoleccion)->toBe('MAATE-DZ8-2026-1691-OF')
        ->and($expediente->nro_permiso_movilizacion)->toBe('GUIA-2026-000417')
        ->and($expediente->documentos_adjuntos)->toHaveCount(2)
        ->and($expediente->estado)->toBe(EstadoSolicitudDeposito::EnBorrador->value);
    foreach ($expediente->documentos_adjuntos as $documento) {
        Storage::disk('local')->assertExists($documento['ruta']);
    }

    $matrices = app(MatrizEspeciesRepositoryInterface::class);
    $matriz = MatrizEspecies::crear(
        id: $matrices->nextIdentity(),
        solicitudId: (string) $solicitud->id(),
        camposDwCPresentes: ['scientificName' => true, 'basisOfRecord' => true],
        tipoTramite: TipoTramite::Deposito->value,
    );
    $registro = $matriz->agregarRegistroEspecimen(
        'Danaus plexippus',
        datosDwC: [
            'scientificName' => 'Danaus plexippus',
            'basisOfRecord' => 'PreservedSpecimen',
        ],
    );
    $matriz->validarRegistroCatalogado($registro);
    $matrices->guardar($matriz);

    $this->actingAs($depositante)->post(
        route('depositos.solicitud.firmar', (string) $solicitud->id()),
        [
            'pdf_firmado' => UploadedFile::fake()->createWithContent(
                'solicitud-firmada.pdf',
                "%PDF-1.7\nsolicitud firmada localmente para E2E",
            ),
        ],
        ['Accept' => 'application/json'],
    )->assertOk();

    $expediente = $expediente->fresh();
    expect($expediente->solicitud_firmada_ruta)->not->toBeNull()
        ->and($expediente->solicitud_firmada_sha256)->not->toBeNull()
        ->and($expediente->solicitud_firma_metadata['firmante_usuario_id'])->toBe((string) $depositante->id);

    app(EnviarSolicitudDepositoHandler::class)(new EnviarSolicitudDepositoInput(
        solicitudId: (string) $solicitud->id(),
    ));
    expect($expediente->fresh()->estado)->toBe(EstadoSolicitudDeposito::PendienteDeRevisionPorCuraduria->value);
    Notification::assertSentTo($curador, NuevaSolicitudPorRevisarNotification::class);

    app(AprobarDocumentalmenteSolicitudHandler::class)(new AprobarDocumentalmenteSolicitudInput(
        solicitudId: (string) $solicitud->id(),
        curadorId: (string) $curador->id,
    ));
    $expediente = $expediente->fresh();
    expect($expediente->estado)->toBe(EstadoSolicitudDeposito::AprobadaDocumentalmente->value)
        ->and($expediente->codigo_qr)->toMatch('/^LOTE-[A-Z0-9]{6}$/');

    $this->actingAs($receptor)
        ->get(route('prestamos.lote.resolver', $expediente->codigo_qr))
        ->assertRedirect(route('prestamos.receptor.deposito.recepcion', (string) $solicitud->id()));
    $this->actingAs($curador)
        ->get(route('prestamos.lote.resolver', $expediente->codigo_qr))
        ->assertRedirect(route('prestamos.curador.deposito.revisar', (string) $solicitud->id()));
    $this->actingAs($depositante)
        ->get(route('prestamos.lote.resolver', $expediente->codigo_qr))
        ->assertRedirect(route('prestamos.investigador.deposito.detalle', (string) $solicitud->id()));

    $this->actingAs($receptor)
        ->get(route('prestamos.receptor.deposito.recepcion', (string) $solicitud->id()))
        ->assertOk();
    $this->actingAs($curador)
        ->get(route('prestamos.receptor.deposito.recepcion', (string) $solicitud->id()))
        ->assertForbidden();

    $recepcion = RecepcionLoteEloquentModel::query()
        ->where('solicitud_deposito_id', (string) $solicitud->id())
        ->sole();
    expect($recepcion->estado)->toBe(EstadoRecepcionLote::EnVerificacion->value);

    $resultadoRecepcion = app(AprobarRecepcionLoteHandler::class)(new AprobarRecepcionLoteInput(
        solicitudId: (string) $solicitud->id(),
        curadorId: (string) $receptor->id,
        itemsVerificacion: array_map(
            static fn (ItemChecklistRecepcion $item): array => [
                'item' => $item->value,
                'resultado' => $item->resultadoConforme(),
            ],
            ItemChecklistRecepcion::cases(),
        ),
    ));
    expect($resultadoRecepcion)->toBeInstanceOf(AprobarRecepcionLoteOutput::class)
        ->and($recepcion->fresh()->estado)->toBe(EstadoRecepcionLote::VerificadoFisicamente->value);
    Notification::assertSentTo($curador, LoteRecibidoParaActaNotification::class);

    $this->actingAs($curador)
        ->get(route('prestamos.curador.deposito.acta', (string) $solicitud->id()))
        ->assertOk();
    app(GenerarActaRecepcionHandler::class)(new GenerarActaRecepcionInput(
        solicitudId: (string) $solicitud->id(),
        curadorId: (string) $curador->id,
    ));

    $this->actingAs($curador)->post(
        route('prestamos.curador.deposito.acta.firmar', (string) $solicitud->id()),
        [
            'pdf_firmado' => UploadedFile::fake()->createWithContent(
                'acta-final-firmada.pdf',
                "%PDF-1.7\nacta final firmada localmente para E2E",
            ),
        ],
        ['Accept' => 'application/json'],
    )->assertOk();

    $recepcion = $recepcion->fresh();
    expect($recepcion->acta_firmada_ruta)->not->toBeNull()
        ->and($recepcion->firma_metadata['firmante_usuario_id'])->toBe((string) $curador->id)
        ->and($recepcion->firma_metadata['proposito'])->toBe('acta_final_recepcion');
    Storage::disk('local')->assertExists($recepcion->acta_firmada_ruta);

    $this->actingAs($depositante)
        ->get(route('prestamos.deposito.acta-recepcion', (string) $solicitud->id()))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
