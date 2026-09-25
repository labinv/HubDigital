<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Illuminate\Support\Facades\DB;

/** Parámetros excepcionales administrados en PostgreSQL; no descarga ni guarda CRL. */
final class PrepararFuentesRevocacionFirma
{
    /** @return array{ocsp: list<string>, crl: list<string>, timeout: int} */
    public function preparar(): array
    {
        $fuentes = DB::table('recepciones.fuentes_revocacion_firma')->where('activo', true)->get();
        $ocsp = [];
        $crl = [];
        $timeout = 2;
        foreach ($fuentes as $fuente) {
            $patron = trim((string) $fuente->patron_emisor);
            $espera = max(1, min(5, (int) $fuente->tiempo_espera_segundos));
            $timeout = max($timeout, $espera);
            $ocspUrl = trim((string) $fuente->ocsp_url);
            if ($patron !== '' && preg_match('~^https?://~i', $ocspUrl) === 1) {
                $ocsp[] = $patron.'='.$ocspUrl;
            }
            $crlUrl = trim((string) $fuente->crl_url);
            if ($patron !== '' && preg_match('~^https?://~i', $crlUrl) === 1) {
                $crl[] = $patron.'='.$crlUrl;
            }
        }

        return ['ocsp' => $ocsp, 'crl' => $crl, 'timeout' => $timeout];
    }
}
