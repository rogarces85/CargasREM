<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DAT-26 notificaciones_enviadas — RF-032..RF-034, LIM-014..015. */
class NotificacionEnviada extends Model
{
    protected $table = 'notificaciones_enviadas';

    protected $fillable = ['usuario_id', 'establecimiento_id', 'tipo', 'canal', 'fecha', 'referencia', 'resultado'];

    protected $casts = [
        'usuario_id' => 'int',
        'establecimiento_id' => 'int',
        'fecha' => 'datetime',
    ];
}
