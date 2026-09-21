<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Illuminate\Support\Facades\File;

/**
 * Crea temporales documentales únicamente bajo el directorio configurado.
 * Cada operación recibe un directorio aleatorio, 0700, y es responsable de
 * eliminarlo en finally; el limpiador de tmpfiles atiende sólo terminaciones
 * abruptas que impidan ejecutar ese finally.
 */
final class DirectorioTemporalHubDigital
{
    public static function crear(string $proposito, int $reservaBytes = 0): string
    {
        self::validarProposito($proposito);
        $raiz = self::raiz();
        self::asegurarEspacio($raiz, $reservaBytes);

        $directorio = $raiz.DIRECTORY_SEPARATOR.$proposito.'-'.bin2hex(random_bytes(12));
        if (! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            throw new \RuntimeException('No se pudo crear el directorio temporal documental.');
        }

        return $directorio;
    }

    public static function crearArchivo(string $proposito, string $prefijo, int $reservaBytes = 0): string
    {
        $directorio = self::crear($proposito, $reservaBytes);
        $archivo = tempnam($directorio, $prefijo);
        if ($archivo === false) {
            self::eliminar($directorio);
            throw new \RuntimeException('No se pudo crear el archivo temporal documental.');
        }
        @chmod($archivo, 0600);

        return $archivo;
    }

    public static function eliminar(string $directorio): void
    {
        $raizSinSeparador = rtrim(self::raiz(), DIRECTORY_SEPARATOR);
        $raiz = $raizSinSeparador.DIRECTORY_SEPARATOR;
        $real = realpath($directorio);
        if ($real !== false && self::esHijoDeRaiz($real, $raizSinSeparador, $raiz)) {
            File::deleteDirectory($real);
        }
    }

    /** @return array<string, string> */
    public static function entornoProcesos(): array
    {
        $raiz = self::raiz();

        return [
            'LANG' => 'C',
            'HOME' => $raiz,
            'TMPDIR' => $raiz,
            'TMP' => $raiz,
            'TEMP' => $raiz,
            'XDG_CACHE_HOME' => $raiz,
        ];
    }

    private static function raiz(): string
    {
        $raiz = rtrim((string) config('hubdigital.temporary_directory'), DIRECTORY_SEPARATOR);
        if ($raiz === '' || ! self::esRutaAbsoluta($raiz)) {
            throw new \RuntimeException('El directorio temporal documental debe ser absoluto.');
        }
        if (DIRECTORY_SEPARATOR === chr(92)) {
            // tempnam() y realpath() devuelven barras invertidas en Windows.
            // Normalizar antes de comparar prefijos evita dejar temporales por
            // una ruta configurada con separadores mixtos.
            $raiz = str_replace('/', DIRECTORY_SEPARATOR, $raiz);
        }
        File::ensureDirectoryExists($raiz, 0700, true);
        @chmod($raiz, 0700);

        return $raiz;
    }

    private static function asegurarEspacio(string $raiz, int $reservaBytes): void
    {
        $disponible = @disk_free_space($raiz);
        $requerido = max(0, $reservaBytes) + max(0, (int) config('hubdigital.temporary_min_free_bytes'));
        if ($disponible === false || $disponible < $requerido) {
            throw new \RuntimeException('No hay espacio temporal suficiente para procesar el documento con seguridad.');
        }
    }

    private static function validarProposito(string $proposito): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,48}$/', $proposito) !== 1) {
            throw new \InvalidArgumentException('El propósito temporal no es válido.');
        }
    }

    private static function esRutaAbsoluta(string $ruta): bool
    {
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $ruta) === 1 || str_starts_with($ruta, chr(92))) {
            return true;
        }

        return str_starts_with($ruta, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $ruta) === 1
            || str_starts_with($ruta, '\\\\');
    }

    private static function esHijoDeRaiz(string $directorio, string $raizSinSeparador, string $raiz): bool
    {
        if (DIRECTORY_SEPARATOR === chr(92)) {
            $directorio = strtolower($directorio);
            $raizSinSeparador = strtolower($raizSinSeparador);
            $raiz = strtolower($raiz);
        }

        $normalizar = static fn (string $ruta): string => DIRECTORY_SEPARATOR === '\\\\' ? strtolower($ruta) : $ruta;

        return $normalizar($directorio) !== $normalizar($raizSinSeparador)
            && str_starts_with($normalizar($directorio.DIRECTORY_SEPARATOR), $normalizar($raiz));
    }
}
