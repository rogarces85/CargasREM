<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DAT-24 generaciones_minsal — envíos de archivos al Minsal (RF-024..RF-026). */
class GeneracionMinsal extends Model
{
    protected $table = 'generaciones_minsal';

    protected $fillable = ['anio', 'serie', 'usuario_id', 'fecha', 'tamano_bytes', 'hash_sha256',
        'filas_datos', 'filas_control', 'resultado', 'mensaje'];

    protected $casts = [
        'anio' => 'int',
        'fecha' => 'datetime',
        'tamano_bytes' => 'int',
        'filas_datos' => 'int',
        'filas_control' => 'int',
        'usuario_id' => 'int',
    ];

    public function serieRegistro()
    {
        return $this->belongsTo(Serie::class, 'serie', 'codigo');
    }
}
