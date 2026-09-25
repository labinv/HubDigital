<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

/** Rechaza PDF corruptos y archivos peligrosos antes de almacenarlos. */
final class ValidadorPdfDeposito
{
    public function validar(string $ruta): void
    {
        if (! is_file($ruta) || filesize($ruta) === 0) {
            throw new \InvalidArgumentException('El PDF está vacío o no puede leerse.');
        }
        $cabecera = file_get_contents($ruta, false, null, 0, 8);
        $cola = file_get_contents($ruta, false, null, max(0, filesize($ruta) - 4096));
        if (preg_match('/\A%PDF-[12]\.\d/', (string) $cabecera) !== 1) {
            throw new \InvalidArgumentException('El archivo no comienza con el número mágico de PDF.');
        }
        if (! str_contains((string) $cola, '%%EOF')) {
            throw new \InvalidArgumentException('El archivo no tiene una estructura PDF completa.');
        }

        try {
            $pdf = new Fpdi;
            if ($pdf->setSourceFile($ruta) < 1) {
                throw new \InvalidArgumentException('El PDF no contiene páginas válidas.');
            }
        } catch (\Throwable $error) {
            throw new \InvalidArgumentException('El PDF está dañado o no puede abrirse.', previous: $error);
        }

        $jar = (string) config('firma-electronica.java_signature_jar');
        if (! is_file($jar)) {
            throw new \InvalidArgumentException('No está disponible el inspector de seguridad de PDF.');
        }
        try {
            $inspector = new Process(['java', '-jar', $jar, 'inspect', $ruta]);
            $inspector->setTimeout(15);
            $inspector->run();
            $resultado = json_decode(trim($inspector->getOutput()), true);
        } catch (\Throwable $error) {
            throw new \InvalidArgumentException('No se pudo inspeccionar el contenido activo del PDF.', previous: $error);
        }
        if (! $inspector->isSuccessful() || ($resultado['status'] ?? null) !== 'seguro') {
            throw new \InvalidArgumentException('El PDF contiene acciones activas, JavaScript o una estructura no permitida.');
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            try {
                $estructura = new Process(['qpdf', '--check', $ruta]);
                $estructura->setTimeout(30);
                $estructura->run();
            } catch (\Throwable $error) {
                throw new \InvalidArgumentException('No se pudo verificar la estructura del PDF.', previous: $error);
            }
            if (! $estructura->isSuccessful()) {
                throw new \InvalidArgumentException('El PDF está dañado o contiene una estructura no permitida.');
            }
        }

    }
}
