<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Modules\GestionPrestamosRecepciones\Presentation\Support\PerfilFirmaPdf;

/** Comprueba objetos PDF analizados por qpdf, nunca coordenadas enviadas por el cliente. */
final class EstructuraFirmaVisiblePdf
{
    /** @param array<string, mixed> $original @param array<string, mixed> $firmado */
    public function coincide(array $original, array $firmado): bool
    {
        try {
            $base = $this->inspeccionar($original, false);
            $firma = $this->inspeccionar($firmado, true);
            if ($base['paginas'] !== $firma['paginas']
                || count($base['anotaciones']) !== 2
                || count($firma['anotaciones']) !== 1) {
                return false;
            }

            $marcadores = [];
            foreach ($base['anotaciones'] as $anotacion) {
                $diccionario = $anotacion['diccionario'];
                $accion = $this->resolver($diccionario['/A'] ?? null, $base['objetos']);
                if (($diccionario['/Subtype'] ?? null) !== '/Link'
                    || ! is_array($accion) || ($accion['/S'] ?? null) !== '/URI'
                    || ! is_string($accion['/URI'] ?? null)
                    || isset($diccionario['/AP']) || isset($diccionario['/Dest'])) {
                    return false;
                }
                $uri = $accion['/URI'];
                if (isset($marcadores[$uri])) {
                    return false;
                }
                $marcadores[$uri] = $anotacion;
            }

            foreach ([PerfilFirmaPdf::solicitudDepositante(), PerfilFirmaPdf::actaRecepcionCurador()] as $perfil) {
                $bloque = $marcadores['u:'.$perfil['bloque']] ?? null;
                $zona = $marcadores['u:'.$perfil['zona']] ?? null;
                if ($bloque !== null && $zona !== null) {
                    return $this->widgetEnZona($bloque, $zona, $base, $firma);
                }
            }

            return false;
        } catch (\UnexpectedValueException) {
            return false;
        }
    }

    /** @param array<string, mixed> $pdf @return array<string, mixed> */
    private function inspeccionar(array $pdf, bool $firmado): array
    {
        $objetos = $pdf['qpdf'][1] ?? null;
        if (($pdf['qpdf'][0]['jsonversion'] ?? null) !== 2 || ! is_array($objetos)) {
            throw new \UnexpectedValueException('Se requiere qpdf JSON v2.');
        }
        $trailer = $objetos['trailer']['value'] ?? [];
        if (isset($trailer['/Encrypt'])) {
            throw new \UnexpectedValueException('El PDF no puede estar cifrado.');
        }
        $catalogo = $this->resolver($trailer['/Root'] ?? null, $objetos);
        if (! is_array($catalogo) || ($catalogo['/Type'] ?? null) !== '/Catalog') {
            throw new \UnexpectedValueException('Catalogo PDF invalido.');
        }

        $visitados = [];
        $this->sinAccionesPeligrosas($catalogo, $objetos, $visitados, $firmado);
        $paginas = [];
        $anotaciones = [];
        $visitados = [];
        $this->paginas($catalogo['/Pages'] ?? null, $objetos, [], $paginas, $anotaciones, $visitados);
        if ($paginas === []) {
            throw new \UnexpectedValueException('El PDF no contiene paginas.');
        }

        $formulario = $this->resolver($catalogo['/AcroForm'] ?? [], $objetos);
        if (! is_array($formulario)) {
            throw new \UnexpectedValueException('Formulario PDF invalido.');
        }
        $campos = $this->resolver($formulario['/Fields'] ?? [], $objetos);
        $esperados = $firmado && count($anotaciones) === 1 ? [$anotaciones[0]['referencia']] : [];
        if ($campos !== $esperados || ($formulario['/NeedAppearances'] ?? false) !== false) {
            throw new \UnexpectedValueException('Campos ajenos a la firma esperada.');
        }

        return compact('objetos', 'paginas', 'anotaciones');
    }

    /** Recorre referencias reales; textos, comentarios y objetos huerfanos no son anotaciones. */
    private function sinAccionesPeligrosas(mixed $valor, array $objetos, array &$visitados, bool $firmado, int $profundidad = 0): void
    {
        if ($profundidad > 100) {
            throw new \UnexpectedValueException('Grafo PDF demasiado profundo.');
        }
        if ($this->esReferencia($valor)) {
            if (isset($visitados[$valor])) {
                return;
            }
            $visitados[$valor] = true;
            $valor = $this->resolver($valor, $objetos);
        }
        if (! is_array($valor)) {
            return;
        }
        foreach ($valor as $clave => $hijo) {
            if (in_array($clave, ['/JavaScript', '/JS', '/Launch', '/EmbeddedFiles', '/RichMedia', '/XFA', '/OpenAction', '/AA', '/Next', '/EF', '/AF', '/OCProperties'], true)
                || ($clave === '/A' && ($firmado || ($valor['/Subtype'] ?? null) !== '/Link'))
                || ($clave === '/S' && $hijo !== '/URI' && in_array($hijo, ['/GoToR', '/GoToE', '/Launch', '/JavaScript', '/SubmitForm', '/ImportData', '/Rendition'], true))) {
                throw new \UnexpectedValueException('Accion no permitida en el PDF.');
            }
            $this->sinAccionesPeligrosas($hijo, $objetos, $visitados, $firmado, $profundidad + 1);
        }
    }

    private function paginas(mixed $referencia, array $objetos, array $heredados, array &$paginas, array &$anotaciones, array &$visitados): void
    {
        if (! $this->esReferencia($referencia) || isset($visitados[$referencia]) || count($visitados) > 1000) {
            throw new \UnexpectedValueException('Arbol de paginas invalido.');
        }
        $visitados[$referencia] = true;
        $pagina = $this->resolver($referencia, $objetos);
        if (! is_array($pagina)) {
            throw new \UnexpectedValueException('Pagina PDF invalida.');
        }
        foreach (['/MediaBox', '/CropBox', '/Rotate'] as $clave) {
            if (array_key_exists($clave, $pagina)) {
                $heredados[$clave] = $this->resolver($pagina[$clave], $objetos);
            }
        }
        if (($pagina['/Type'] ?? null) === '/Pages') {
            $hijos = $this->resolver($pagina['/Kids'] ?? null, $objetos);
            if (! is_array($hijos) || ! array_is_list($hijos)) {
                throw new \UnexpectedValueException('Arbol de paginas sin hijos.');
            }
            foreach ($hijos as $hijo) {
                $this->paginas($hijo, $objetos, $heredados, $paginas, $anotaciones, $visitados);
            }

            return;
        }
        if (($pagina['/Type'] ?? null) !== '/Page') {
            throw new \UnexpectedValueException('Tipo de pagina invalido.');
        }
        $media = $this->rectangulo($heredados['/MediaBox'] ?? null);
        $crop = $this->rectangulo($heredados['/CropBox'] ?? $media);
        $rotacion = $heredados['/Rotate'] ?? 0;
        if (! is_numeric($rotacion) || (float) $rotacion != 0.0 || (float) ($pagina['/UserUnit'] ?? 1) != 1.0) {
            throw new \UnexpectedValueException('La plantilla no admite paginas rotadas o escaladas.');
        }
        $indice = count($paginas);
        $paginas[] = ['media' => $media, 'crop' => $crop];
        $lista = $this->resolver($pagina['/Annots'] ?? [], $objetos);
        if (! is_array($lista) || ! array_is_list($lista)) {
            throw new \UnexpectedValueException('Anotaciones invalidas.');
        }
        foreach ($lista as $ref) {
            $diccionario = $this->resolver($ref, $objetos);
            if (! $this->esReferencia($ref) || ! is_array($diccionario)
                || ($diccionario['/Type'] ?? null) !== '/Annot'
                || (isset($diccionario['/P']) && $diccionario['/P'] !== $referencia)) {
                throw new \UnexpectedValueException('Anotacion ajena a su pagina.');
            }
            $anotaciones[] = [
                'referencia' => $ref, 'pagina' => $indice, 'paginaRef' => $referencia,
                'rect' => $this->rectangulo($this->resolver($diccionario['/Rect'] ?? null, $objetos)),
                'diccionario' => $diccionario,
            ];
        }
    }

    private function widgetEnZona(array $bloque, array $zona, array $base, array $firma): bool
    {
        $widget = $firma['anotaciones'][0];
        $diccionario = $widget['diccionario'];
        if ($bloque['pagina'] !== $zona['pagina'] || $widget['pagina'] !== $zona['pagina']
            || ! $this->coordenadasIguales($widget['rect'], $zona['rect'])
            || ($diccionario['/Subtype'] ?? null) !== '/Widget'
            || ($diccionario['/FT'] ?? null) !== '/Sig'
            || ($diccionario['/T'] ?? null) !== 'u:Signature1'
            || ($diccionario['/F'] ?? null) !== 4
            || ($diccionario['/P'] ?? null) !== $widget['paginaRef']
            || array_diff(array_keys($diccionario), ['/Type', '/Subtype', '/FT', '/Rect', '/V', '/T', '/F', '/P', '/AP']) !== []) {
            return false;
        }

        [$bx1, $by1, $bx2, $by2] = $bloque['rect'];
        [$zx1, $zy1, $zx2, $zy2] = $zona['rect'];
        [$px1, $py1, $px2, $py2] = $base['paginas'][$zona['pagina']]['crop'];
        if ($bx1 < $px1 + 24 || $by1 < $py1 + 42 || $bx2 > $px2 - 24 || $by2 > $py2 - 24
            || $bx2 - $bx1 < 220 || $by2 - $by1 < 54 || $zx2 - $zx1 < 200 || $zy2 - $zy1 < 45
            || $zx1 < $bx1 + 2 || $zy1 < $by1 + 2 || $zx2 > $bx2 - 2 || $zy2 > $by2 - 2) {
            return false;
        }

        $valor = $this->resolver($diccionario['/V'] ?? null, $firma['objetos']);
        $apariencias = $this->resolver($diccionario['/AP'] ?? null, $firma['objetos']);
        if (! is_array($valor) || ($valor['/Type'] ?? null) !== '/Sig'
            || ($valor['/SubFilter'] ?? null) !== '/ETSI.CAdES.detached'
            || ! is_array($apariencias) || array_keys($apariencias) !== ['/N']
            || ! $this->esReferencia($apariencias['/N'])) {
            return false;
        }
        $stream = $firma['objetos']['obj:'.$apariencias['/N']]['stream'] ?? null;
        $apariencia = $stream['dict'] ?? null;

        return is_array($apariencia)
            && ($apariencia['/Type'] ?? null) === '/XObject'
            && ($apariencia['/Subtype'] ?? null) === '/Form'
            && ! isset($apariencia['/OC'])
            && $this->coordenadasIguales($this->rectangulo($apariencia['/BBox'] ?? null), [0, 0, $zx2 - $zx1, $zy2 - $zy1])
            && $this->coordenadasIguales($apariencia['/Matrix'] ?? [1, 0, 0, 1, 0, 0], [1, 0, 0, 1, 0, 0]);
    }

    private function resolver(mixed $valor, array $objetos): mixed
    {
        $visitados = [];
        while ($this->esReferencia($valor)) {
            if (isset($visitados[$valor]) || ! isset($objetos['obj:'.$valor]) || count($visitados) > 100) {
                throw new \UnexpectedValueException('Referencia PDF invalida.');
            }
            $visitados[$valor] = true;
            $objeto = $objetos['obj:'.$valor];
            $valor = $objeto['value'] ?? $objeto['stream']['dict'] ?? null;
        }

        return $valor;
    }

    private function esReferencia(mixed $valor): bool
    {
        return is_string($valor) && preg_match('/^\d+ \d+ R$/D', $valor) === 1;
    }

    /** @return list<float> */
    private function rectangulo(mixed $valor): array
    {
        if (! is_array($valor) || ! array_is_list($valor) || count($valor) !== 4) {
            throw new \UnexpectedValueException('Rectangulo PDF invalido.');
        }
        foreach ($valor as $numero) {
            if ((! is_int($numero) && ! is_float($numero)) || ! is_finite((float) $numero)) {
                throw new \UnexpectedValueException('Coordenada PDF invalida.');
            }
        }
        [$x1, $y1, $x2, $y2] = array_map(floatval(...), $valor);
        if ($x1 >= $x2 || $y1 >= $y2) {
            throw new \UnexpectedValueException('Rectangulo PDF vacio o invertido.');
        }

        return [$x1, $y1, $x2, $y2];
    }

    private function coordenadasIguales(mixed $actual, array $esperado): bool
    {
        if (! is_array($actual) || ! array_is_list($actual) || count($actual) !== count($esperado)) {
            return false;
        }
        foreach ($esperado as $indice => $numero) {
            if ((! is_int($actual[$indice]) && ! is_float($actual[$indice]))
                || ! is_finite((float) $actual[$indice]) || abs($actual[$indice] - $numero) > 0.02) {
                return false;
            }
        }

        return true;
    }
}
