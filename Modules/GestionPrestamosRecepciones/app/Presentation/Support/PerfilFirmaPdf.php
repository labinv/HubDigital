<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

/**
 * Contrato de marcadores que une una plantilla PDF con el firmante esperado.
 *
 * Cada plantilla incorpora dos enlaces PDF que el firmador elimina antes de
 * firmar: el bloque nominal completo y la zona interior donde debe aparecer el
 * sello. La doble marca permite comprobar que la firma no fue desplazada a un
 * pie de pagina o a otra zona del documento.
 */
final class PerfilFirmaPdf
{
    public const SOLICITUD_DEPOSITANTE = 'solicitud-deposito:depositante:v1';

    public const ACTA_RECEPCION_CURADOR = 'acta-recepcion:curador:v1';

    private const BASE_URI = 'https://firmas.hubdigital.invalid/';

    /** @return array{perfil: string, rol: string, bloque: string, zona: string} */
    public static function solicitudDepositante(): array
    {
        return self::marcadores(self::SOLICITUD_DEPOSITANTE, 'depositante');
    }

    /** @return array{perfil: string, rol: string, bloque: string, zona: string} */
    public static function actaRecepcionCurador(): array
    {
        return self::marcadores(self::ACTA_RECEPCION_CURADOR, 'curador');
    }

    /** @return list<string> */
    public static function urisPermitidas(): array
    {
        $perfiles = [self::solicitudDepositante(), self::actaRecepcionCurador()];

        return array_values(array_merge(...array_map(
            static fn (array $perfil): array => [$perfil['bloque'], $perfil['zona']],
            $perfiles,
        )));
    }

    /** @return array{perfil: string, rol: string, bloque: string, zona: string} */
    private static function marcadores(string $perfil, string $rol): array
    {
        $ruta = str_replace([':', '_'], ['/', '-'], $perfil);

        return [
            'perfil' => $perfil,
            'rol' => $rol,
            'bloque' => self::BASE_URI.'bloques/'.$ruta,
            'zona' => self::BASE_URI.'zonas/'.$ruta,
        ];
    }
}
