<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent para la tabla 'recepciones.recepcion_lotes'.
 *
 * Representa la persistencia de la recepción física de un lote de muestras.
 */
final class RecepcionLoteEloquentModel extends Model
{
    protected $table = 'recepciones.recepcion_lotes';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'solicitud_deposito_id',
        'codigo_qr',
        'tipo_tramite',
        'estado',
        'estado_coleccion',
        'motivo_fallo',
        'accion_correctiva',
        'items_verificacion',
        'observaciones',
        'acta_recepcion',
        'acta_firmada_ruta',
        'firmada_en',
        'recibido_por',
        'verificado_en',
        'suspendido_en',
        'acta_generada_por',
        'acta_generada_en',
        'firma_metadata',
    ];

    protected $casts = [
        'items_verificacion' => 'array',
        'observaciones' => 'array',
        'acta_recepcion' => 'array',
        'firmada_en' => 'datetime',
        'verificado_en' => 'datetime',
        'suspendido_en' => 'datetime',
        'acta_generada_en' => 'datetime',
        'firma_metadata' => 'array',
    ];
}
