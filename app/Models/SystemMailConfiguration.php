<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SystemMailConfiguration extends Model
{
    protected $table = 'usuarios.configuracion_correo';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'host',
        'port',
        'username',
        'password',
        'from_address',
        'from_name',
        'updated_by',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }
}
