<?php

namespace App\Models;

use App\Models\Concerns\ScopeServicioActivo;
use Illuminate\Database\Eloquent\Model;

/** DAT-14 datos_control — hojas de control por serie (RF-003 RN-019). */
class DatosControl extends Model
{
    use ScopeServicioActivo;

    protected $table = 'datos_control';

    protected $fillable = ['servicio_id', 'serie', 'mes', 'anio', 'establecimiento_id', 'carga_id'];

    protected $casts = [
        'servicio_id' => 'int',
        'mes' => 'int',
        'anio' => 'int',
        'carga_id' => 'int',
    ];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function serieRegistro()
    {
        return $this->belongsTo(Serie::class, 'serie', 'codigo');
    }

    public function carga()
    {
        return $this->belongsTo(Carga::class);
    }
}
