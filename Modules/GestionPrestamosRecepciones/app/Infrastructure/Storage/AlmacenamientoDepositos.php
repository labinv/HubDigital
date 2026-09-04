<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Fachada unica para los objetos privados del expediente de deposito. */
final class AlmacenamientoDepositos
{
    private ?R2S3Client $r2 = null;

    public function driver(): string
    {
        $seleccionado = strtolower(trim((string) config('deposit-storage.driver', 'auto')));
        if (! in_array($seleccionado, ['auto', 'local', 'r2'], true)) {
            throw new \RuntimeException('DEPOSIT_STORAGE_DRIVER debe ser auto, local o r2.');
        }

        $configR2 = (array) config('deposit-storage.r2', []);
        $campos = ['endpoint', 'bucket', 'access_key_id', 'secret_access_key'];
        $presentes = array_filter($campos, static fn (string $campo): bool => trim((string) ($configR2[$campo] ?? '')) !== '');
        $r2Completo = count($presentes) === count($campos);

        if ($seleccionado === 'r2' && ! $r2Completo) {
            throw new \RuntimeException('R2 fue exigido pero faltan endpoint, bucket o credenciales S3.');
        }
        if ($seleccionado === 'auto' && $presentes !== [] && ! $r2Completo) {
            throw new \RuntimeException('La configuracion R2 esta incompleta; no se aplicara fallback silencioso.');
        }

        $driver = $seleccionado === 'auto' ? ($r2Completo ? 'r2' : 'local') : $seleccionado;
        if ($driver === 'local' && (bool) config('deposit-storage.require_remote', false)) {
            throw new \RuntimeException('Este ambiente exige R2 y no permite almacenamiento local de expedientes.');
        }
        if ($driver === 'local' && ! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('El fallback local de expedientes solo esta permitido en local o testing.');
        }

        return $driver;
    }

    public function guardarArchivo(UploadedFile $archivo, string $directorio): string
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        $nombre = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $ruta = trim($directorio, '/').'/'.$nombre;
        $contenido = file_get_contents($archivo->getRealPath());
        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el archivo cargado.');
        }
        $this->guardarContenido($ruta, $contenido, $archivo->getMimeType() ?: 'application/octet-stream');

        return $ruta;
    }

    public function guardarSubidoComo(UploadedFile $archivo, string $ruta): string
    {
        $contenido = file_get_contents($archivo->getRealPath());
        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el archivo cargado.');
        }
        $this->guardarContenido($ruta, $contenido, $archivo->getMimeType() ?: 'application/pdf');

        return $ruta;
    }

    public function guardarContenido(string $ruta, string $contenido, string $mime = 'application/octet-stream'): void
    {
        $ruta = $this->normalizarRuta($ruta);
        $maximo = (int) config('deposit-storage.max_object_bytes', 25 * 1024 * 1024);
        if (strlen($contenido) > $maximo) {
            throw new \RuntimeException("El objeto excede el limite interno de {$maximo} bytes.");
        }
        if ($this->driver() === 'r2') {
            $this->clienteR2()->put($ruta, $contenido, $mime);
            if ((bool) config('deposit-storage.verify_after_write', true)) {
                $cabecera = $this->clienteR2()->head($ruta);
                if ($cabecera['content_length'] !== null && $cabecera['content_length'] !== strlen($contenido)) {
                    $this->clienteR2()->delete($ruta);
                    throw new \RuntimeException('R2 no confirmo el tamano integro del objeto guardado.');
                }
            }

            return;
        }

        $guardado = Storage::disk($this->discoLocal())->put($ruta, $contenido);
        if ($guardado !== true) {
            throw new \RuntimeException('No se pudo guardar el documento en el disco privado local.');
        }
    }

    public function existe(string $ruta): bool
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2' && $this->clienteR2()->exists($ruta)) {
            return true;
        }
        if ($this->driver() === 'local' && Storage::disk($this->discoLocal())->exists($ruta)) {
            return true;
        }

        return Storage::disk($this->discoPublicoLegado())->exists($ruta);
    }

    public function obtener(string $ruta): string
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2' && $this->clienteR2()->exists($ruta)) {
            return $this->clienteR2()->get($ruta);
        }
        if ($this->driver() === 'local' && Storage::disk($this->discoLocal())->exists($ruta)) {
            return Storage::disk($this->discoLocal())->get($ruta);
        }
        if (Storage::disk($this->discoPublicoLegado())->exists($ruta)) {
            return Storage::disk($this->discoPublicoLegado())->get($ruta);
        }

        throw new \RuntimeException('El documento solicitado no existe en el almacenamiento privado.');
    }

    /** @return resource */
    public function readStream(string $ruta)
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2' && $this->clienteR2()->exists($ruta)) {
            return $this->clienteR2()->readStream($ruta);
        }
        if (Storage::disk($this->discoLocal())->exists($ruta)) {
            $stream = Storage::disk($this->discoLocal())->readStream($ruta);
            if (is_resource($stream)) {
                return $stream;
            }
        }
        $stream = Storage::disk($this->discoPublicoLegado())->readStream($ruta);
        if (! is_resource($stream)) {
            throw new \RuntimeException('No se pudo abrir el documento privado para lectura.');
        }

        return $stream;
    }

    public function eliminar(string $ruta): void
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2' && $this->clienteR2()->exists($ruta)) {
            $this->clienteR2()->delete($ruta);
        }
        Storage::disk($this->discoLocal())->delete($ruta);
        Storage::disk($this->discoPublicoLegado())->delete($ruta);
    }

    public function mimeType(string $ruta): string
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2' && $this->clienteR2()->exists($ruta)) {
            return (string) ($this->clienteR2()->head($ruta)['content_type'] ?: 'application/octet-stream');
        }
        if (Storage::disk($this->discoLocal())->exists($ruta)) {
            return Storage::disk($this->discoLocal())->mimeType($ruta) ?: 'application/octet-stream';
        }

        return Storage::disk($this->discoPublicoLegado())->mimeType($ruta) ?: 'application/octet-stream';
    }

    public function sha256(string $ruta): string
    {
        $stream = $this->readStream($ruta);
        try {
            $contexto = hash_init('sha256');
            hash_update_stream($contexto, $stream);

            return hash_final($contexto);
        } finally {
            fclose($stream);
        }
    }

    public function copiaLocal(string $ruta): ArchivoLocalDeposito
    {
        $ruta = $this->normalizarRuta($ruta);
        $driver = $this->driver();
        if ($driver === 'local' && Storage::disk($this->discoLocal())->exists($ruta)) {
            return new ArchivoLocalDeposito(Storage::disk($this->discoLocal())->path($ruta), false);
        }
        if ($driver === 'local' && Storage::disk($this->discoPublicoLegado())->exists($ruta)) {
            return new ArchivoLocalDeposito(Storage::disk($this->discoPublicoLegado())->path($ruta), false);
        }

        // En R2 siempre se materializa primero el objeto remoto autoritativo. Una
        // copia publica heredada nunca debe prevalecer sobre el expediente remoto.
        $contenido = $this->obtener($ruta);
        $directorio = (string) config('deposit-storage.temporary_directory');
        File::ensureDirectoryExists($directorio, 0700, true);
        $temporal = $directorio.DIRECTORY_SEPARATOR.Str::uuid().'-'.basename($ruta);
        if (file_put_contents($temporal, $contenido, LOCK_EX) === false) {
            throw new \RuntimeException('No se pudo crear la copia local temporal del objeto R2.');
        }
        @chmod($temporal, 0600);

        return new ArchivoLocalDeposito($temporal, true);
    }

    private function clienteR2(): R2S3Client
    {
        return $this->r2 ??= new R2S3Client((array) config('deposit-storage.r2'));
    }

    private function discoLocal(): string
    {
        return (string) config('deposit-storage.local_disk', 'local');
    }

    private function discoPublicoLegado(): string
    {
        return (string) config('deposit-storage.legacy_public_disk', 'public');
    }

    private function normalizarRuta(string $ruta): string
    {
        $ruta = trim($ruta);
        $segmentos = explode('/', $ruta);
        if ($ruta === '' || str_starts_with($ruta, '/') || str_contains($ruta, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $ruta) === 1
            || in_array('.', $segmentos, true) || in_array('..', $segmentos, true)
        ) {
            throw new \InvalidArgumentException('La ruta del objeto de deposito no es valida.');
        }

        return implode('/', array_filter($segmentos, static fn (string $segmento): bool => $segmento !== ''));
    }
}
