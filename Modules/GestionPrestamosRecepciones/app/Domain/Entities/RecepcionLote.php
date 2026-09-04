<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Entities;

use DateTimeImmutable;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaDigitalRecepcionEmitida;
use Modules\GestionPrestamosRecepciones\Domain\Events\ActaRecepcionFirmada;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteIniciada;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteSuspendida;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteVerificadaConObservaciones;
use Modules\GestionPrestamosRecepciones\Domain\Events\RecepcionLoteVerificadaFisicamente;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\TransicionEstadoInvalida;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\AccionCorrectiva;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\CodigoQRLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DocumentoAdjunto;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoColeccion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\EstadoRecepcionLote;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemChecklistRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ItemVerificacionRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\MotivoFalloRecepcion;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\RecepcionLoteId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\SolicitudDepositoId;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\TipoTramite;

/**
 * Agregado raíz de la recepción física de un lote de muestras entregado por el
 * investigador tras la aprobación documental de su solicitud.
 *
 * El lote se identifica ante el sistema con un {@see RecepcionLoteId} (UUID) y ante
 * las personas con el {@see CodigoQRLote} que lo rotula (clave natural asignada en la
 * aprobación documental). Nace {@see EstadoRecepcionLote::EnVerificacion} vía
 * {@see iniciar()} y transiciona según la decisión del curador sobre la lista de
 * verificación:
 *  - {@see verificarConforme()}   → Verificado Físicamente (custodia constatada),
 *  - {@see rechazarPorAnomaliaSubsanable()} → Recepción Suspendida (devolución, QR vigente),
 *  - {@see aceptarConObservaciones()} → Verificado con Observaciones (custodia constatada).
 *
 * El alta en la colección se habilita únicamente al adjuntar el acta final firmada.
 *
 * Construir vía {@see iniciar()}; rehidratar vía {@see reconstituir()}.
 */
final class RecepcionLote
{
    // ── Identidad ────────────────────────────────────────────────

    private RecepcionLoteId $id;

    private SolicitudDepositoId $solicitudId;

    private CodigoQRLote $codigoQR;

    // ── Datos del lote ───────────────────────────────────────────

    private TipoTramite $tipoTramite;

    private EstadoRecepcionLote $estado;

    /** @var list<ItemVerificacionRecepcion> */
    private array $itemsVerificacion = [];

    /** @var list<string> */
    private array $observaciones = [];

    private ?EstadoColeccion $estadoColeccion = null;

    private ?MotivoFalloRecepcion $motivoFallo = null;

    private ?AccionCorrectiva $accionCorrectiva = null;

    private ?DocumentoAdjunto $actaRecepcion = null;

    /** Ruta privada del PDF firmado electrónicamente por el curador. */
    private ?string $actaFirmadaRuta = null;

    private ?string $recibidoPor = null;

    private ?string $actaGeneradaPor = null;

    /** @var array<string, mixed> */
    private array $firmaMetadata = [];

    // ── Hitos de negocio ─────────────────────────────────────────

    private ?DateTimeImmutable $verificadoEn = null;

    private ?DateTimeImmutable $suspendidoEn = null;

    private ?DateTimeImmutable $firmadaEn = null;

    private ?DateTimeImmutable $actaGeneradaEn = null;

    /** @var object[] */
    private array $events = [];

    private function __construct() {}

    // ── Factories ────────────────────────────────────────────────

    /**
     * Inicia la recepción física del lote entregado. El lote queda a la espera de la
     * evaluación del curador ({@see EstadoRecepcionLote::EnVerificacion}).
     */
    public static function iniciar(
        RecepcionLoteId $id,
        SolicitudDepositoId $solicitudId,
        CodigoQRLote $codigoQR,
        TipoTramite $tipoTramite,
    ): self {
        $lote = new self;
        $lote->id = $id;
        $lote->solicitudId = $solicitudId;
        $lote->codigoQR = $codigoQR;
        $lote->tipoTramite = $tipoTramite;
        $lote->estado = EstadoRecepcionLote::EnVerificacion;

        $lote->events[] = new RecepcionLoteIniciada(
            solicitudId: $solicitudId,
            codigoQR: (string) $codigoQR,
            ocurridoEn: new DateTimeImmutable,
        );

        return $lote;
    }

    /**
     * Rehidrata el agregado desde persistencia. Bypasea las invariantes del negocio y
     * no emite eventos. Usar ÚNICAMENTE en repositorios al reconstruir desde la base
     * de datos.
     *
     * @param  list<ItemVerificacionRecepcion>  $itemsVerificacion
     * @param  list<string>  $observaciones
     */
    public static function reconstituir(
        RecepcionLoteId $id,
        SolicitudDepositoId $solicitudId,
        CodigoQRLote $codigoQR,
        TipoTramite $tipoTramite,
        EstadoRecepcionLote $estado,
        array $itemsVerificacion = [],
        array $observaciones = [],
        ?EstadoColeccion $estadoColeccion = null,
        ?MotivoFalloRecepcion $motivoFallo = null,
        ?AccionCorrectiva $accionCorrectiva = null,
        ?DocumentoAdjunto $actaRecepcion = null,
        ?DateTimeImmutable $verificadoEn = null,
        ?DateTimeImmutable $suspendidoEn = null,
        ?string $actaFirmadaRuta = null,
        ?DateTimeImmutable $firmadaEn = null,
        ?string $recibidoPor = null,
        ?string $actaGeneradaPor = null,
        array $firmaMetadata = [],
        ?DateTimeImmutable $actaGeneradaEn = null,
    ): self {
        $lote = new self;
        $lote->id = $id;
        $lote->solicitudId = $solicitudId;
        $lote->codigoQR = $codigoQR;
        $lote->tipoTramite = $tipoTramite;
        $lote->estado = $estado;
        $lote->itemsVerificacion = $itemsVerificacion;
        $lote->observaciones = $observaciones;
        $lote->estadoColeccion = $estadoColeccion;
        $lote->motivoFallo = $motivoFallo;
        $lote->accionCorrectiva = $accionCorrectiva;
        $lote->actaRecepcion = $actaRecepcion;
        $lote->verificadoEn = $verificadoEn;
        $lote->suspendidoEn = $suspendidoEn;
        $lote->actaFirmadaRuta = $actaFirmadaRuta;
        $lote->firmadaEn = $firmadaEn;
        $lote->recibidoPor = $recibidoPor;
        $lote->actaGeneradaPor = $actaGeneradaPor;
        $lote->firmaMetadata = $firmaMetadata;
        $lote->actaGeneradaEn = $actaGeneradaEn;

        return $lote;
    }

    // ── Comportamiento ───────────────────────────────────────────

    /**
     * Aprueba la recepción cuando el lote cumple todos los ítems de la lista de
     * verificación. Registra el régimen de tenencia propuesto; el alta en colección
     * queda pendiente del acta final firmada por curaduría.
     *
     * @param  list<ItemVerificacionRecepcion>  $items
     */
    public function verificarConforme(array $items, string $receptorId): void
    {
        $this->garantizarEnVerificacion('verificarConforme');

        if ($items === []) {
            throw new \DomainException('No es posible aprobar la recepción sin una lista de verificación cumplida');
        }

        $ahora = new DateTimeImmutable;
        $this->itemsVerificacion = array_values($items);
        $this->estado = EstadoRecepcionLote::VerificadoFisicamente;
        $this->estadoColeccion = EstadoColeccion::porTipoTramite($this->tipoTramite);
        $this->verificadoEn = $ahora;
        $this->recibidoPor = $this->actorValido($receptorId);

        $this->events[] = new RecepcionLoteVerificadaFisicamente(
            solicitudId: $this->solicitudId,
            estadoColeccion: $this->estadoColeccion,
            ocurridoEn: $ahora,
        );
    }

    /**
     * Suspende la recepción por una anomalía subsanable y ordena la acción correctiva
     * correspondiente para el investigador. El Código QR del lote permanece vigente
     * para reintentar la recepción del mismo lote.
     */
    public function rechazarPorAnomaliaSubsanable(MotivoFalloRecepcion $motivo): void
    {
        $this->garantizarEnVerificacion('rechazarPorAnomaliaSubsanable');

        $ahora = new DateTimeImmutable;
        $this->estado = EstadoRecepcionLote::RecepcionSuspendida;
        $this->motivoFallo = $motivo;
        $this->accionCorrectiva = $motivo->accionCorrectiva();
        $this->suspendidoEn = $ahora;

        $this->events[] = new RecepcionLoteSuspendida(
            solicitudId: $this->solicitudId,
            motivoFallo: $motivo,
            accionCorrectiva: $this->accionCorrectiva,
            ocurridoEn: $ahora,
        );
    }

    /**
     * Reabre la verificación de un lote suspendido para reintentar su recepción con el
     * mismo Código QR, una vez subsanada la anomalía.
     */
    public function reintentarVerificacion(): void
    {
        if (! $this->estado->equals(EstadoRecepcionLote::RecepcionSuspendida)) {
            throw TransicionEstadoInvalida::de($this->estado->value, 'reintentarVerificacion');
        }

        $this->estado = EstadoRecepcionLote::EnVerificacion;
        $this->motivoFallo = null;
        $this->accionCorrectiva = null;
        $this->suspendidoEn = null;
    }

    /**
     * Acepta la recepción con observaciones cuando la anomalía no puede devolverse al
     * investigador. Las observaciones se derivan de los ítems no conformes del checklist
     * (más un comentario opcional) y se incorporan al acta final; el régimen será
     * cuarentena si algún ítem no conforme compromete su sanidad.
     *
     * @param  list<ItemChecklistRecepcion>  $itemsNoConformes
     */
    public function aceptarConObservaciones(array $itemsNoConformes, ?string $comentario = null, ?string $receptorId = null): void
    {
        $this->garantizarEnVerificacion('aceptarConObservaciones');

        if ($itemsNoConformes === []) {
            throw new \DomainException('La aceptación con observaciones requiere al menos un ítem no conforme');
        }

        $ahora = new DateTimeImmutable;
        $this->estado = EstadoRecepcionLote::VerificadoConObservaciones;
        $this->verificadoEn = $ahora;
        $this->recibidoPor = $this->actorValido($receptorId ?? '');

        foreach ($itemsNoConformes as $item) {
            $this->observaciones[] = $item->value;
        }

        $comentario = $comentario !== null ? trim($comentario) : null;
        if ($comentario !== null && $comentario !== '') {
            $this->observaciones[] = $comentario;
        }

        $comprometeSanidad = false;
        foreach ($itemsNoConformes as $item) {
            if ($item->comprometeSanidad()) {
                $comprometeSanidad = true;
                break;
            }
        }

        $this->estadoColeccion = $comprometeSanidad
            ? EstadoColeccion::Cuarentena
            : EstadoColeccion::porTipoTramite($this->tipoTramite);

        $this->events[] = new RecepcionLoteVerificadaConObservaciones(
            solicitudId: $this->solicitudId,
            estadoColeccion: $this->estadoColeccion,
            ocurridoEn: $ahora,
        );
    }

    /**
     * El curador genera el acta solo despues de que recepcion EPN constato el lote.
     */
    public function generarActaRecepcion(string $curadorId): void
    {
        if (! in_array($this->estado, [EstadoRecepcionLote::VerificadoFisicamente, EstadoRecepcionLote::VerificadoConObservaciones], true)) {
            throw new \DomainException('El acta solo puede generarse despues de recibir y constatar el lote.');
        }

        if ($this->actaEmitida()) {
            return;
        }

        $ahora = new DateTimeImmutable;
        $this->actaGeneradaPor = $this->actorValido($curadorId);
        $this->actaGeneradaEn = $ahora;
        $this->emitirActaRecepcion($ahora);
    }

    /**
     * Adjunta al acta el PDF firmado electrónicamente por el curador. La
     * validación de que el PDF trae una firma electrónica real es responsabilidad del
     * caso de uso (mediante el puerto de validación de firma); el agregado solo exige
     * que el acta ya haya sido emitida (recepción finalizada).
     */
    public function adjuntarActaFirmada(string $rutaPdfFirmado, array $firmaMetadata = []): void
    {
        if (! $this->actaEmitida()) {
            throw new \DomainException('No es posible firmar un acta que aún no ha sido emitida');
        }

        if ($this->actaFirmada()) {
            throw new \DomainException('El acta ya fue firmada y el expediente está cerrado');
        }

        $ruta = trim($rutaPdfFirmado);
        if ($ruta === '') {
            throw new \InvalidArgumentException('La ruta del acta firmada no puede estar vacía');
        }

        $ahora = new DateTimeImmutable;
        $this->actaFirmadaRuta = $ruta;
        $this->firmadaEn = $ahora;
        $this->firmaMetadata = $firmaMetadata;

        $this->events[] = new ActaRecepcionFirmada(
            solicitudId: $this->solicitudId,
            ruta: $ruta,
            estadoColeccion: $this->estadoColeccion
                ?? throw new \DomainException('El lote firmado no tiene un régimen de colección definido'),
            ocurridoEn: $ahora,
        );
    }

    // ── Consultas ────────────────────────────────────────────────

    public function id(): RecepcionLoteId
    {
        return $this->id;
    }

    public function solicitudId(): SolicitudDepositoId
    {
        return $this->solicitudId;
    }

    public function codigoQR(): CodigoQRLote
    {
        return $this->codigoQR;
    }

    public function tipoTramite(): TipoTramite
    {
        return $this->tipoTramite;
    }

    public function estado(): EstadoRecepcionLote
    {
        return $this->estado;
    }

    public function estadoColeccion(): ?EstadoColeccion
    {
        return $this->estadoColeccion;
    }

    /** @return list<ItemVerificacionRecepcion> */
    public function itemsVerificacion(): array
    {
        return $this->itemsVerificacion;
    }

    /** @return list<string> */
    public function observaciones(): array
    {
        return $this->observaciones;
    }

    public function motivoFallo(): ?MotivoFalloRecepcion
    {
        return $this->motivoFallo;
    }

    public function accionCorrectiva(): ?AccionCorrectiva
    {
        return $this->accionCorrectiva;
    }

    public function actaEmitida(): bool
    {
        return $this->actaRecepcion !== null;
    }

    public function actaRecepcion(): ?DocumentoAdjunto
    {
        return $this->actaRecepcion;
    }

    public function actaFirmada(): bool
    {
        return $this->actaFirmadaRuta !== null;
    }

    public function actaFirmadaRuta(): ?string
    {
        return $this->actaFirmadaRuta;
    }

    public function firmadaEn(): ?DateTimeImmutable
    {
        return $this->firmadaEn;
    }

    public function recibidoPor(): ?string
    {
        return $this->recibidoPor;
    }

    public function actaGeneradaPor(): ?string
    {
        return $this->actaGeneradaPor;
    }

    public function actaGeneradaEn(): ?DateTimeImmutable
    {
        return $this->actaGeneradaEn;
    }

    public function verificadoEn(): ?DateTimeImmutable
    {
        return $this->verificadoEn;
    }

    public function suspendidoEn(): ?DateTimeImmutable
    {
        return $this->suspendidoEn;
    }

    /** @return array<string, mixed> */
    public function firmaMetadata(): array
    {
        return $this->firmaMetadata;
    }

    /**
     * Extrae y vacía la cola interna de eventos. El Handler los publica tras guardar.
     *
     * @return object[]
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    // ── Helpers privados ─────────────────────────────────────────

    private function garantizarEnVerificacion(string $accion): void
    {
        if (! $this->estado->equals(EstadoRecepcionLote::EnVerificacion)) {
            throw TransicionEstadoInvalida::de($this->estado->value, $accion);
        }
    }

    private function actorValido(string $actorId): string
    {
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw new \InvalidArgumentException('El funcionario responsable es obligatorio.');
        }

        return $actorId;
    }

    private function emitirActaRecepcion(DateTimeImmutable $ahora): void
    {
        $ruta = 'actas/recepcion/'.((string) $this->id).'.pdf';
        $this->actaRecepcion = DocumentoAdjunto::of('Acta Digital de Recepción', $ruta);

        $this->events[] = new ActaDigitalRecepcionEmitida(
            solicitudId: $this->solicitudId,
            ruta: $ruta,
            ocurridoEn: $ahora,
        );
    }
}
