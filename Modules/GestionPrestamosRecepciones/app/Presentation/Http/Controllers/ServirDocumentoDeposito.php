<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers;

use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve, mediante streaming autenticado, un documento adjunto a una solicitud de
 * depósito o donación.
 *
 * Acceso permitido solo al curador, receptor EPN o depositante dueño de la solicitud. El
 * documento se identifica por su índice posicional dentro de `documentos_cargados`
 * (las claves son etiquetas legibles con espacios/acentos, no aptas para la URL).
 * Se entrega `inline` para previsualizarse en el navegador; con `?descargar=1` se
 * entrega como descarga.
 */
final class ServirDocumentoDeposito
{
    public function __invoke(string $id, int $indice, AlmacenamientoDepositos $almacenamiento): StreamedResponse
    {
        $user = auth()->user();

        $deposito = SolicitudDepositoEloquentModel::find($id);
        abort_if($deposito === null, 404);

        $esCurador = $user?->esCurador() ?? false;
        $esReceptor = $user?->esReceptor() ?? false;
        $esDueno = (string) $deposito->investigador_id === (string) $user?->id;
        abort_unless($esCurador || $esReceptor || $esDueno, 403);

        $documentos = $deposito->documentos_cargados ?? [];
        $rutas = array_values($documentos);
        $claves = array_keys($documentos);
        abort_unless(isset($rutas[$indice]), 404);

        $ruta = $rutas[$indice];
        abort_unless(is_string($ruta) && $ruta !== '', 404);
        abort_unless($almacenamiento->existe($ruta), 404);

        $nombre = ($deposito->nombres_archivos_originales ?? [])[$claves[$indice]] ?? basename($ruta);
        $nombre = preg_replace('/[\x00-\x1F\x7F"\\\\]/u', '_', basename((string) $nombre)) ?: 'documento.pdf';
        if (! str_ends_with(mb_strtolower($nombre), '.pdf')) {
            $nombre .= '.pdf';
        }
        $disposicion = request()->boolean('descargar') ? 'attachment' : 'inline';
        $stream = $almacenamiento->readStream($ruta);

        $firmaPdf = fread($stream, 5);
        if ($firmaPdf !== '%PDF-') {
            fclose($stream);
            abort(415, 'El archivo almacenado no es un PDF válido.');
        }
        $metadatosStream = stream_get_meta_data($stream);
        if (($metadatosStream['seekable'] ?? false) === true) {
            rewind($stream);
        } else {
            fclose($stream);
            $stream = $almacenamiento->readStream($ruta);
        }

        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) ?: 'documento.pdf';
        $cabeceraDisposicion = (new ResponseHeaderBag)->makeDisposition($disposicion, $nombre, $fallback);

        return response()->stream(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $cabeceraDisposicion,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'; sandbox",
        ]);
    }
}
