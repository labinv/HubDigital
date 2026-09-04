<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Exceptions;

use DomainException;

/**
 * Se lanza cuando el PDF que el curador sube como acta firmada no contiene una firma
 * electrónica válida, según la verificación criptográfica y de contenido.
 */
final class ActaRecepcionSinFirmaElectronica extends DomainException
{
    public static function crear(): self
    {
        return new self('El documento no superó la validación de firma, certificado e integridad de HubDigital.');
    }
}
