<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

test('rechaza cualquier controlador que no sea R2 sin escribir una copia local', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    config()->set('deposit-storage.driver', 'auto');
    config()->set('deposit-storage.require_remote', false);
    config()->set('deposit-storage.r2', []);

    expect(fn () => (new AlmacenamientoDepositos)->driver())
        ->toThrow(RuntimeException::class, 'DEPOSIT_STORAGE_DRIVER debe ser r2')
        ->and(Storage::disk('local')->exists('depositos/prueba.pdf'))->toBeFalse()
        ->and(Storage::disk('public')->exists('depositos/prueba.pdf'))->toBeFalse();
});

test('rechaza configuracion R2 parcial sin aplicar fallback silencioso', function (): void {
    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.r2', ['bucket' => 'solo-bucket']);

    expect(fn () => (new AlmacenamientoDepositos)->driver())
        ->toThrow(RuntimeException::class, 'R2 fue exigido');
});

test('rechaza como PDF un archivo cuyo contenido no tiene cabecera PDF', function (): void {
    Storage::fake('local');

    $archivo = UploadedFile::fake()->createWithContent('aparente.pdf', 'contenido que no es PDF');

    expect(fn () => (new AlmacenamientoDepositos)->guardarArchivo($archivo, 'depositos/prueba'))
        ->toThrow(InvalidArgumentException::class, 'no corresponde a un documento PDF');
    Storage::disk('local')->assertMissing('depositos/prueba');
});

test('acepta un PDF con cabecera valida y lo persiste en R2', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.verify_after_write', false);
    config()->set('deposit-storage.r2', [
        'endpoint' => 'https://cuenta.r2.cloudflarestorage.com',
        'bucket' => 'hubdigital-depositos-dev',
        'access_key_id' => 'clave-prueba',
        'secret_access_key' => 'secreto-prueba',
        'timeout_seconds' => 5,
        'connect_timeout_seconds' => 2,
        'max_attempts' => 1,
    ]);

    $archivo = UploadedFile::fake()->createWithContent('valido.pdf', "%PDF-1.7\ncontenido de prueba");
    $ruta = (new AlmacenamientoDepositos)->guardarArchivo($archivo, 'depositos/prueba');

    expect($ruta)->toStartWith('depositos/prueba/');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT');
});

test('persiste, verifica y elimina un objeto mediante la API S3 de R2', function (): void {
    $objetos = [];
    Http::fake(function (Request $request) use (&$objetos) {
        $ruta = parse_url($request->url(), PHP_URL_PATH);
        if ($request->method() === 'PUT') {
            $objetos[$ruta] = ['body' => $request->body(), 'mime' => 'application/pdf'];

            return Http::response('', 200);
        }
        if ($request->method() === 'HEAD') {
            return isset($objetos[$ruta])
                ? Http::response('', 200, [
                    'Content-Type' => $objetos[$ruta]['mime'],
                    'Content-Length' => (string) strlen($objetos[$ruta]['body']),
                ])
                : Http::response('', 404);
        }
        if ($request->method() === 'GET' && isset($objetos[$ruta])) {
            return Http::response($objetos[$ruta]['body'], 200, ['Content-Type' => $objetos[$ruta]['mime']]);
        }
        if ($request->method() === 'DELETE') {
            unset($objetos[$ruta]);

            return Http::response('', 204);
        }

        return Http::response('', 404);
    });
    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.verify_after_write', true);
    config()->set('deposit-storage.max_object_bytes', 1024);
    config()->set('deposit-storage.r2', [
        'endpoint' => 'https://cuenta.r2.cloudflarestorage.com',
        'bucket' => 'hubdigital-depositos-dev',
        'access_key_id' => 'clave-prueba',
        'secret_access_key' => 'secreto-prueba',
        'timeout_seconds' => 5,
        'connect_timeout_seconds' => 2,
        'max_attempts' => 1,
    ]);

    $almacenamiento = new AlmacenamientoDepositos;
    $almacenamiento->guardarContenido('depositos/expediente.pdf', '%PDF-R2', 'application/pdf');
    expect($almacenamiento->driver())->toBe('r2')
        ->and($almacenamiento->existe('depositos/expediente.pdf'))->toBeTrue()
        ->and($almacenamiento->obtener('depositos/expediente.pdf'))->toBe('%PDF-R2')
        ->and($almacenamiento->sha256('depositos/expediente.pdf'))->toBe(hash('sha256', '%PDF-R2'));

    $almacenamiento->eliminar('depositos/expediente.pdf');
    expect($almacenamiento->existe('depositos/expediente.pdf'))->toBeFalse();
});

test('una escritura R2 fallida no se confirma ni intenta verificar el objeto', function (): void {
    Http::fake([
        '*' => Http::response('fallo de almacenamiento inyectado', 500),
    ]);
    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.verify_after_write', true);
    config()->set('deposit-storage.r2', [
        'endpoint' => 'https://cuenta.r2.cloudflarestorage.com',
        'bucket' => 'hubdigital-depositos-dev',
        'access_key_id' => 'clave-prueba',
        'secret_access_key' => 'secreto-prueba',
        'timeout_seconds' => 5,
        'connect_timeout_seconds' => 2,
        'max_attempts' => 1,
    ]);

    expect(fn () => (new AlmacenamientoDepositos)->guardarContenido(
        'depositos/escritura-fallida.pdf',
        '%PDF-fallo-controlado',
        'application/pdf',
    ))->toThrow(RuntimeException::class);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT');
});

test('r2 ausente no se sustituye por una copia local o publica heredada', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('local')->put('depositos/solo-local.pdf', '%PDF-copia-local');
    Storage::disk('public')->put('depositos/solo-publico.pdf', '%PDF-copia-publica');
    Http::fake(['*' => Http::response('', 404)]);
    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.r2', [
        'endpoint' => 'https://cuenta.r2.cloudflarestorage.com',
        'bucket' => 'hubdigital-depositos-dev',
        'access_key_id' => 'clave-prueba',
        'secret_access_key' => 'secreto-prueba',
        'timeout_seconds' => 5,
        'connect_timeout_seconds' => 2,
        'max_attempts' => 1,
    ]);

    $almacenamiento = new AlmacenamientoDepositos;

    expect($almacenamiento->existe('depositos/solo-local.pdf'))->toBeFalse()
        ->and($almacenamiento->existe('depositos/solo-publico.pdf'))->toBeFalse()
        ->and(fn () => $almacenamiento->obtener('depositos/solo-local.pdf'))
        ->toThrow(RuntimeException::class, 'no existe en Cloudflare R2');
});

test('readStream y mimeType R2 ausentes no consultan copias locales', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    Storage::disk('local')->put('depositos/solo-local.pdf', '%PDF-local');
    Http::fake(['*' => Http::response('', 404)]);
    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.r2', [
        'endpoint' => 'https://cuenta.r2.cloudflarestorage.com', 'bucket' => 'hubdigital-depositos-dev',
        'access_key_id' => 'clave-prueba', 'secret_access_key' => 'secreto-prueba', 'max_attempts' => 1,
    ]);

    $almacenamiento = new AlmacenamientoDepositos;

    expect(fn () => $almacenamiento->readStream('depositos/solo-local.pdf'))
        ->toThrow(RuntimeException::class, 'no existe en Cloudflare R2')
        ->and(fn () => $almacenamiento->mimeType('depositos/solo-local.pdf'))
        ->toThrow(RuntimeException::class, 'no existe en Cloudflare R2');
});

test('un fallo de conexión R2 no activa el almacenamiento local', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('depositos/solo-local.pdf', '%PDF-local');
    Http::fake(['*' => Http::failedConnection()]);
    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.r2', [
        'endpoint' => 'https://cuenta.r2.cloudflarestorage.com', 'bucket' => 'hubdigital-depositos-dev',
        'access_key_id' => 'clave-prueba', 'secret_access_key' => 'secreto-prueba', 'max_attempts' => 1,
    ]);

    expect(fn () => (new AlmacenamientoDepositos)->obtener('depositos/solo-local.pdf'))
        ->toThrow(RuntimeException::class, 'No fue posible conectar de forma segura con R2.');
});

test('eliminar en R2 no borra una copia local ajena cuando el objeto remoto no existe', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('depositos/solo-local.pdf', '%PDF-local');
    Http::fake(['*' => Http::response('', 404)]);
    config()->set('deposit-storage.driver', 'r2');
    config()->set('deposit-storage.require_remote', true);
    config()->set('deposit-storage.r2', [
        'endpoint' => 'https://cuenta.r2.cloudflarestorage.com', 'bucket' => 'hubdigital-depositos-dev',
        'access_key_id' => 'clave-prueba', 'secret_access_key' => 'secreto-prueba', 'max_attempts' => 1,
    ]);

    (new AlmacenamientoDepositos)->eliminar('depositos/solo-local.pdf');

    Storage::disk('local')->assertExists('depositos/solo-local.pdf');
    Http::assertSentCount(1);
});
