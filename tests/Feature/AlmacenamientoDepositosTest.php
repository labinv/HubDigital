<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

test('usa fallback privado local solamente en testing cuando no existen credenciales R2', function (): void {
    Storage::fake('local');
    Storage::fake('public');
    config()->set('deposit-storage.driver', 'auto');
    config()->set('deposit-storage.require_remote', false);
    config()->set('deposit-storage.r2', []);

    $almacenamiento = new AlmacenamientoDepositos;
    $almacenamiento->guardarContenido('depositos/prueba.pdf', '%PDF-prueba', 'application/pdf');

    expect($almacenamiento->driver())->toBe('local')
        ->and($almacenamiento->existe('depositos/prueba.pdf'))->toBeTrue()
        ->and($almacenamiento->obtener('depositos/prueba.pdf'))->toBe('%PDF-prueba')
        ->and($almacenamiento->sha256('depositos/prueba.pdf'))->toBe(hash('sha256', '%PDF-prueba'));
});

test('rechaza configuracion R2 parcial sin aplicar fallback silencioso', function (): void {
    config()->set('deposit-storage.driver', 'auto');
    config()->set('deposit-storage.require_remote', false);
    config()->set('deposit-storage.r2', ['bucket' => 'solo-bucket']);

    expect(fn () => (new AlmacenamientoDepositos)->driver())
        ->toThrow(RuntimeException::class, 'configuracion R2 esta incompleta');
});

test('rechaza como PDF un archivo cuyo contenido no tiene cabecera PDF', function (): void {
    Storage::fake('local');
    config()->set('deposit-storage.driver', 'local');
    config()->set('deposit-storage.require_remote', false);

    $archivo = UploadedFile::fake()->createWithContent('aparente.pdf', 'contenido que no es PDF');

    expect(fn () => (new AlmacenamientoDepositos)->guardarArchivo($archivo, 'depositos/prueba'))
        ->toThrow(InvalidArgumentException::class, 'no corresponde a un documento PDF');
    Storage::disk('local')->assertMissing('depositos/prueba');
});

test('acepta un PDF con cabecera valida dentro del primer kilobyte', function (): void {
    Storage::fake('local');
    config()->set('deposit-storage.driver', 'local');
    config()->set('deposit-storage.require_remote', false);

    $archivo = UploadedFile::fake()->createWithContent('valido.pdf', "%PDF-1.7\ncontenido de prueba");
    $ruta = (new AlmacenamientoDepositos)->guardarArchivo($archivo, 'depositos/prueba');

    Storage::disk('local')->assertExists($ruta);
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
