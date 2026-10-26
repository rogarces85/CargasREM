<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * DAT-01 servicios — PK = idservicio Minsal (23 = Servicio de Salud Osorno).
 */
class Servicio extends Model
{
    protected $fillable = ['id', 'nombre', 'region', 'orden', 'activo'];

    protected $casts = [
        'id' => 'int',
        'region' => 'int',
        'orden' => 'int',
        'activo' => 'boolean',
    ];

    public function establecimientos()
    {
        return $this->hasMany(Establecimiento::class);
    }
}
