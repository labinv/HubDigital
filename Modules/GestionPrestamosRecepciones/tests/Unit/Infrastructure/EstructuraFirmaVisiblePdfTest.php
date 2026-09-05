<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Unit\Infrastructure;

use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\EstructuraFirmaVisiblePdf;
use Modules\GestionPrestamosRecepciones\Presentation\Support\PerfilFirmaPdf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EstructuraFirmaVisiblePdfTest extends TestCase
{
    #[DataProvider('perfiles')]
    public function test_acepta_solo_el_widget_en_la_pagina_y_zona_del_bloque_nominal(array $perfil): void
    {
        [$original, $firmado] = $this->documentos($perfil);

        self::assertTrue((new EstructuraFirmaVisiblePdf)->coincide($original, $firmado));
    }

    public static function perfiles(): iterable
    {
        yield 'depositante' => [PerfilFirmaPdf::solicitudDepositante()];
        yield 'curador' => [PerfilFirmaPdf::actaRecepcionCurador()];
    }

    #[DataProvider('alteraciones')]
    public function test_rechaza_alteraciones_aunque_el_cliente_afirme_que_la_zona_es_correcta(string $alteracion): void
    {
        [$original, $firmado] = $this->documentos(PerfilFirmaPdf::solicitudDepositante());
        $objetos = &$firmado['qpdf'][1];
        $widget = &$objetos['obj:8 0 R']['value'];
        switch ($alteracion) {
            case 'desplazamiento dentro del bloque':
                $widget['/Rect'] = [57, 548, 540, 622];
                break;
            case 'footer generico':
                $widget['/Rect'] = [56, 12, 539, 86];
                break;
            case 'otra pagina':
                $objetos['obj:3 0 R']['value']['/Annots'] = ['8 0 R'];
                $objetos['obj:4 0 R']['value']['/Annots'] = [];
                $widget['/P'] = '3 0 R';
                break;
            case 'referencia de pagina falsa':
                $widget['/P'] = '3 0 R';
                break;
            case 'otra anotacion de subtipo desconocido':
                $objetos['obj:4 0 R']['value']['/Annots'][] = '12 0 R';
                $objetos['obj:12 0 R']['value'] = ['/Type' => '/Annot', '/Subtype' => '/CustomOverlay', '/Rect' => [20, 20, 200, 200]];
                break;
            case 'widget repetido':
                $objetos['obj:4 0 R']['value']['/Annots'][] = '8 0 R';
                break;
            case 'campo desconectado del widget':
                $objetos['obj:9 0 R']['value']['/Fields'] = ['12 0 R'];
                $objetos['obj:12 0 R']['value'] = $widget;
                break;
            case 'sin apariencia':
                unset($widget['/AP']);
                break;
            case 'apariencia no es stream':
                $objetos['obj:11 0 R'] = ['value' => $objetos['obj:11 0 R']['stream']['dict']];
                break;
            case 'apariencia desplazada':
                $objetos['obj:11 0 R']['stream']['dict']['/Matrix'] = [1, 0, 0, 1, 100, 0];
                break;
            case 'apariencia demasiado grande':
                $objetos['obj:11 0 R']['stream']['dict']['/BBox'] = [0, 0, 595, 842];
                break;
            case 'firma oculta':
                $widget['/F'] = 6;
                break;
            case 'firma invisible':
                $widget['/F'] = 5;
                break;
            case 'firma no imprimible':
                $widget['/F'] = 0;
                break;
            case 'regenerar apariencia':
                $objetos['obj:9 0 R']['value']['/NeedAppearances'] = true;
                break;
            case 'script escapado analizado por qpdf':
                $widget['/AA'] = ['/E' => ['/S' => '/JavaScript', '/JS' => 'u:app.alert(1)']];
                break;
            case 'accion externa':
                $widget['/A'] = ['/S' => '/URI', '/URI' => 'u:https://example.invalid'];
                break;
            case 'pagina recortada':
                $objetos['obj:4 0 R']['value']['/CropBox'] = [0, 0, 500, 842];
                break;
            case 'pagina rotada':
                $objetos['obj:4 0 R']['value']['/Rotate'] = 90;
                break;
            case 'marcadores del original en paginas diferentes':
                $original['qpdf'][1]['obj:3 0 R']['value']['/Annots'] = ['5 0 R'];
                $original['qpdf'][1]['obj:4 0 R']['value']['/Annots'] = ['6 0 R'];
                break;
            case 'marcador original duplicado':
                $original['qpdf'][1]['obj:4 0 R']['value']['/Annots'][] = '6 0 R';
                break;
            case 'marcador original de otro rol':
                $original['qpdf'][1]['obj:6 0 R']['value']['/A']['/URI'] = 'u:'.PerfilFirmaPdf::actaRecepcionCurador()['zona'];
                break;
            case 'marcador original fuera del bloque':
                $original['qpdf'][1]['obj:6 0 R']['value']['/Rect'] = [56, 100, 539, 174];
                $widget['/Rect'] = [56, 100, 539, 174];
                break;
            case 'referencia ciclica':
                $objetos['obj:9 0 R']['value']['/Fields'] = '12 0 R';
                $objetos['obj:12 0 R']['value'] = '12 0 R';
                break;
            case 'firma de formato distinto':
                $objetos['obj:10 0 R']['value']['/SubFilter'] = '/adbe.pkcs7.detached';
                break;
        }

        self::assertFalse((new EstructuraFirmaVisiblePdf)->coincide($original, $firmado));
    }

    public static function alteraciones(): iterable
    {
        foreach ([
            'desplazamiento dentro del bloque', 'footer generico', 'otra pagina',
            'referencia de pagina falsa', 'otra anotacion de subtipo desconocido', 'widget repetido',
            'campo desconectado del widget', 'sin apariencia', 'apariencia no es stream',
            'apariencia desplazada', 'apariencia demasiado grande', 'firma oculta',
            'firma invisible', 'firma no imprimible', 'regenerar apariencia',
            'script escapado analizado por qpdf', 'accion externa', 'pagina recortada', 'pagina rotada',
            'marcadores del original en paginas diferentes', 'marcador original duplicado',
            'marcador original de otro rol', 'marcador original fuera del bloque',
            'referencia ciclica', 'firma de formato distinto',
        ] as $alteracion) {
            yield $alteracion => [$alteracion];
        }
    }

    public function test_no_confunde_texto_de_metadatos_ni_marcadores_huerfanos_con_anotaciones(): void
    {
        [$original, $firmado] = $this->documentos(PerfilFirmaPdf::solicitudDepositante());
        $firmado['qpdf'][1]['obj:1 0 R']['value']['/Description'] = 'u:/Subtype /Link /Rect [0 0 595 842] /JavaScript';
        // pdf-lib puede conservar los objetos cuyos enlaces ya elimino de Annots.
        $firmado['qpdf'][1]['obj:5 0 R'] = $original['qpdf'][1]['obj:5 0 R'];
        $firmado['qpdf'][1]['obj:6 0 R'] = $original['qpdf'][1]['obj:6 0 R'];

        self::assertTrue((new EstructuraFirmaVisiblePdf)->coincide($original, $firmado));
    }

    /** Estructuras sinteticas equivalentes a qpdf --json=2, con el bloque en pagina 2. */
    private function documentos(array $perfil): array
    {
        $comunes = [
            'trailer' => ['value' => ['/Root' => '1 0 R']],
            'obj:1 0 R' => ['value' => ['/Type' => '/Catalog', '/Pages' => '2 0 R']],
            'obj:2 0 R' => ['value' => ['/Type' => '/Pages', '/Kids' => ['3 0 R', '4 0 R'], '/Count' => 2, '/MediaBox' => [0, 0, 595, 842]]],
            'obj:3 0 R' => ['value' => ['/Type' => '/Page', '/Parent' => '2 0 R']],
            'obj:4 0 R' => ['value' => ['/Type' => '/Page', '/Parent' => '2 0 R']],
        ];
        $original = $comunes;
        $original['obj:4 0 R']['value']['/Annots'] = ['5 0 R', '6 0 R'];
        foreach ([5 => ['bloque', [48, 540, 547, 630]], 6 => ['zona', [56, 548, 539, 622]]] as $id => [$tipo, $rect]) {
            $original['obj:'.$id.' 0 R']['value'] = [
                '/Type' => '/Annot', '/Subtype' => '/Link', '/Rect' => $rect,
                '/A' => ['/S' => '/URI', '/URI' => 'u:'.$perfil[$tipo]],
            ];
        }
        $firmado = $comunes;
        $firmado['obj:1 0 R']['value']['/AcroForm'] = '9 0 R';
        $firmado['obj:4 0 R']['value']['/Annots'] = ['8 0 R'];
        $firmado['obj:8 0 R']['value'] = [
            '/Type' => '/Annot', '/Subtype' => '/Widget', '/FT' => '/Sig',
            '/Rect' => [56, 548, 539, 622], '/V' => '10 0 R', '/T' => 'u:Signature1',
            '/F' => 4, '/P' => '4 0 R', '/AP' => ['/N' => '11 0 R'],
        ];
        $firmado['obj:9 0 R']['value'] = ['/Fields' => ['8 0 R'], '/SigFlags' => 3];
        $firmado['obj:10 0 R']['value'] = ['/Type' => '/Sig', '/SubFilter' => '/ETSI.CAdES.detached'];
        $firmado['obj:11 0 R']['stream']['dict'] = [
            '/Type' => '/XObject', '/Subtype' => '/Form', '/FormType' => 1,
            '/BBox' => [0, 0, 483, 74], '/Matrix' => [1, 0, 0, 1, 0, 0], '/Resources' => [],
        ];

        return [
            ['qpdf' => [['jsonversion' => 2], $original]],
            ['qpdf' => [['jsonversion' => 2], $firmado]],
        ];
    }
}
