<?php

namespace App\Models;

use App\Enums\TipoObservacion;
use Illuminate\Database\Eloquent\Model;

/** DAT-11 recepcion_observaciones — RF-031, RN-061/RN-062. */
class RecepcionObservacion extends Model
{
    protected $table = 'recepcion_observaciones';
    protected $fillable = ['recepcion_id', 'version_cerrada', 'autor_id', 'autor_rol', 'tipo', 'texto'];

    protected $casts = [
        'tipo' => TipoObservacion::class,
        'autor_id' => 'int',
        'version_cerrada' => 'int',
    ];

    public function recepcion()
    {
        return $this->belongsTo(Recepcion::class);
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'autor_id');
    }
}
