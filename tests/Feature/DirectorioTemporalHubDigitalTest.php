<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;

beforeEach(function (): void {
    $this->temporaryRoot = storage_path('framework/testing/hubdigital-temp');
    File::deleteDirectory($this->temporaryRoot);
    config()->set('hubdigital.temporary_directory', $this->temporaryRoot);
    config()->set('hubdigital.temporary_min_free_bytes', 0);
});

afterEach(function (): void {
    File::deleteDirectory($this->temporaryRoot);
});

test('crea y limpia un temporal documental dentro de la raiz configurada', function (): void {
    $archivo = DirectorioTemporalHubDigital::crearArchivo('ocr', 'original-', 1024);
    $directorio = dirname($archivo);

    $raizEsperada = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->temporaryRoot), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

    expect($archivo)->toStartWith($raizEsperada)
        ->and(is_file($archivo))->toBeTrue();

    DirectorioTemporalHubDigital::eliminar($directorio);

    expect(is_dir($directorio))->toBeFalse();
});

test('no elimina una ruta que no pertenece a la raiz temporal documental', function (): void {
    $ajeno = storage_path('framework/testing/hubdigital-ajeno');
    File::ensureDirectoryExists($ajeno, 0700, true);

    DirectorioTemporalHubDigital::eliminar($ajeno);

    expect(is_dir($ajeno))->toBeTrue();
    File::deleteDirectory($ajeno);
});
