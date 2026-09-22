<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaDigitalmente;

use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaNoPerteneceAlInvestigador;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\FirmaBase64Invalida;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\PatenteAnualNoConfigurada;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\PatenteAnualRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\SolicitudPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Throwable;

final class FirmarActaDigitalmenteHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly SolicitudPrestamoRepositoryInterface $solicitudRepo,
        private readonly PdfGeneratorPort $pdfGenerator,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
        private readonly PatenteAnualRepositoryInterface $patentes,
    ) {}

    /**
     * @throws ActaPrestamoNoEncontradaException
     * @throws ActaNoPerteneceAlInvestigador
     * @throws FirmaBase64Invalida
     */
    public function handle(FirmarActaDigitalmenteInput $input): FirmarActaDigitalmenteOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaId);
        $acta = $this->actaRepo->buscarPorId($actaId);

        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $solicitud = $this->solicitudRepo->buscarPorId($acta->solicitudPrestamoId());
        if ($solicitud === null || $solicitud->investigadorId() !== $input->investigadorId) {
            throw ActaNoPerteneceAlInvestigador::conActaId($actaId);
        }

        $firmaContenido = $this->validarFirmaBase64($input->firmaBase64);
        $anioPatente = (int) $acta->fechaInicio()->format('Y');
        if ($this->patentes->buscarCodigoPorAnio($anioPatente) === null) {
            throw PatenteAnualNoConfigurada::paraAnio($anioPatente);
        }

        $firmaImagenRuta = 'firmas-investigador/'.(string) $actaId.'/'.Str::uuid().'.png';
        try {
            $this->pdfGenerator->almacenarImagenPng(
                base64: $input->firmaBase64,
                rutaDestino: $firmaImagenRuta,
            );

            // El PDF firmado se genera al vuelo. La firma nueva queda aislada en
            // una clave inmutable y solo se vuelve oficial al confirmar PostgreSQL.
            $acta->firmarDigitalmente($firmaImagenRuta, hash('sha256', $firmaContenido));

            $this->transactionManager->executeTransactional(function () use ($acta): void {
                $this->actaRepo->guardar($acta);
                foreach ($acta->pullEvents() as $event) {
                    $this->publisher->publish($event);
                }
            });
        } catch (Throwable $e) {
            if (! $this->actaRepo->rutaEstaReferenciada($firmaImagenRuta)) {
                $this->eliminarSinOcultarError($firmaImagenRuta);
            }

            throw $e;
        }

        return FirmarActaDigitalmenteOutput::fromPrimitives($acta);
    }

    private function validarFirmaBase64(string $firmaBase64): string
    {
        if (! str_starts_with($firmaBase64, 'data:image/png;base64,')) {
            throw FirmaBase64Invalida::formatoInvalido();
        }

        $base64Data = str_replace(' ', '+', substr($firmaBase64, strpos($firmaBase64, ',') + 1));
        $contenido = base64_decode($base64Data, strict: true);
        if ($contenido === false) {
            throw FirmaBase64Invalida::decodificacionFallida();
        }

        return $contenido;
    }

    private function eliminarSinOcultarError(string $ruta): void
    {
        try {
            $this->pdfGenerator->eliminar($ruta);
        } catch (Throwable $cleanupError) {
            report($cleanupError);
        }
    }
}
