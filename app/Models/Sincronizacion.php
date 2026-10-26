<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DAT-25 sincronizaciones — sync de catálogos Minsal (RF-023, RN-032). */
class Sincronizacion extends Model
{
    protected $table = 'sincronizaciones';

    protected $fillable = ['tipo', 'fuente', 'comenzado_en', 'terminado_en', 'tablas_resumen',
        'estado', 'usuario_id', 'error'];

    protected $casts = [
        'comenzado_en' => 'datetime',
        'terminado_en' => 'datetime',
        'tablas_resumen' => 'array',
        'usuario_id' => 'int',
    ];
}
