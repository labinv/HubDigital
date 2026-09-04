<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\R2S3Client;

test('firma una operacion R2 con SigV4, ruta RFC3986 y payload SHA-256', function (): void {
    Http::fake(['*' => Http::response('', 200)]);
    $cliente = new R2S3Client([
        'endpoint' => 'https://cuenta-prueba.r2.cloudflarestorage.com',
        'bucket' => 'mepn-privado',
        'access_key_id' => 'AKIDPRUEBA',
        'secret_access_key' => 'secreto-que-no-debe-salir',
        'timeout_seconds' => 5,
        'connect_timeout_seconds' => 2,
        'max_attempts' => 1,
    ], new DateTimeImmutable('2026-09-04T12:34:56Z'));

    $contenido = '%PDF-prueba';
    $cliente->put('documentos con espacio/area ñ.pdf', $contenido, 'application/pdf');

    Http::assertSent(function (Request $request) use ($contenido): bool {
        $autorizacion = $request->header('Authorization')[0] ?? '';

        return $request->method() === 'PUT'
            && $request->url() === 'https://cuenta-prueba.r2.cloudflarestorage.com/mepn-privado/documentos%20con%20espacio/area%20%C3%B1.pdf'
            && ($request->header('x-amz-date')[0] ?? '') === '20260904T123456Z'
            && ($request->header('x-amz-content-sha256')[0] ?? '') === hash('sha256', $contenido)
            && str_contains($autorizacion, 'Credential=AKIDPRUEBA/20260904/auto/s3/aws4_request')
            && str_contains($autorizacion, 'SignedHeaders=host;x-amz-content-sha256;x-amz-date')
            && ! str_contains($autorizacion, 'secreto-que-no-debe-salir');
    });
});

test('rechaza un endpoint R2 sin HTTPS antes de enviar credenciales', function (): void {
    Http::fake();
    $cliente = new R2S3Client([
        'endpoint' => 'http://cuenta-prueba.r2.cloudflarestorage.com',
        'bucket' => 'mepn-privado',
        'access_key_id' => 'AKIDPRUEBA',
        'secret_access_key' => 'secreto',
    ]);

    expect(fn () => $cliente->get('objeto.pdf'))
        ->toThrow(RuntimeException::class, 'debe usar HTTPS');
    Http::assertNothingSent();
});
