<?php

namespace App\Models;

use App\Models\Concerns\ScopeServicioActivo;
use Illuminate\Database\Eloquent\Model;

/** Staging de hojas de control idem datos_carga. */
class DatosControlCarga extends Model
{
    use ScopeServicioActivo;

    public $timestamps = false;

    protected $table = 'datos_control_carga';

    protected $guarded = ['id'];

    protected $casts = [
        'carga_id' => 'int',
        'servicio_id' => 'int',
        'mes' => 'int',
        'anio' => 'int',
    ];

    public function carga()
    {
        return $this->belongsTo(Carga::class);
    }

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }
}
