<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\UseCases\FirmarActaCuradorDigitalmente;

use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Application\Ports\EventPublisherPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\TransactionManagerPort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarActaDocumento\ConsultarActaDocumentoInput;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\ActaPrestamoNoEncontradaException;
use Modules\GestionPrestamosRecepciones\Domain\Exceptions\FirmaBase64Invalida;
use Modules\GestionPrestamosRecepciones\Domain\Repositories\ActaPrestamoRepositoryInterface;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ActaPrestamoId;
use Throwable;

final class FirmarActaCuradorDigitalmenteHandler
{
    public function __construct(
        private readonly ActaPrestamoRepositoryInterface $actaRepo,
        private readonly PdfGeneratorPort $pdfGenerator,
        private readonly EventPublisherPort $publisher,
        private readonly TransactionManagerPort $transactionManager,
        private readonly ConsultarActaDocumentoHandler $actaDocumento,
    ) {}

    /**
     * @throws ActaPrestamoNoEncontradaException
     * @throws FirmaBase64Invalida
     */
    public function handle(FirmarActaCuradorDigitalmenteInput $input): FirmarActaCuradorDigitalmenteOutput
    {
        $actaId = ActaPrestamoId::fromString($input->actaId);
        $acta = $this->actaRepo->buscarPorId($actaId);
        if ($acta === null) {
            throw ActaPrestamoNoEncontradaException::conId($actaId);
        }

        $this->validarFirmaBase64($input->firmaBase64);
        $firmadoInvestigador = $acta->pdfFirmadoRuta();
        $firmaInvestigadorBase64 = ($firmadoInvestigador !== null
            && str_starts_with($firmadoInvestigador, 'firmas-investigador/'))
            ? $this->pdfGenerator->leerImagenBase64($firmadoInvestigador, $acta->pdfFirmadoSha256())
            : null;

        $documento = $this->actaDocumento->handle(
            new ConsultarActaDocumentoInput(actaId: (string) $actaId),
        );
        $rutaSalida = 'actas-firmadas-curador/'.(string) $actaId.'/'.Str::uuid().'.pdf';

        try {
            // La firma del curador se incrusta directamente; no necesita otro PNG
            // persistente que quedaria sin referencia en PostgreSQL.
            $sha256Salida = $this->pdfGenerator->generarActaYAlmacenar(
                datos: [
                    'acta' => $documento,
                    'firmaBase64' => $firmaInvestigadorBase64,
                    'firmaCuradorBase64' => $input->firmaBase64,
                ],
                rutaDestino: $rutaSalida,
            );

            $acta->validarConFirmaCurador(
                curadorId: $input->curadorId,
                pdfFirmadoCuradorRuta: $rutaSalida,
                pdfFirmadoCuradorSha256: $sha256Salida,
            );

            $this->transactionManager->executeTransactional(function () use ($acta): void {
                $this->actaRepo->guardar($acta);
                foreach ($acta->pullEvents() as $event) {
                    $this->publisher->publish($event);
                }
            });
        } catch (Throwable $e) {
            if (! $this->actaRepo->rutaEstaReferenciada($rutaSalida)) {
                $this->eliminarSinOcultarError($rutaSalida);
            }

            throw $e;
        }

        return FirmarActaCuradorDigitalmenteOutput::fromPrimitives($acta);
    }

    private function validarFirmaBase64(string $firmaBase64): void
    {
        if (! str_starts_with($firmaBase64, 'data:image/png;base64,')) {
            throw FirmaBase64Invalida::formatoInvalido();
        }

        $base64Data = str_replace(' ', '+', substr($firmaBase64, strpos($firmaBase64, ',') + 1));
        if (base64_decode($base64Data, strict: true) === false) {
            throw FirmaBase64Invalida::decodificacionFallida();
        }
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
