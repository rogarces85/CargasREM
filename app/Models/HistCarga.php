<?php

namespace App\Models;

use App\Models\Concerns\AislamientoEstablecimiento;
use Illuminate\Database\Eloquent\Model;

/** DAT-15 hist_carga — histórico (RF-016/RN-038 y matriz EstabCargados RF-007). */
class HistCarga extends Model
{
    use AislamientoEstablecimiento;

    protected $table = 'hist_carga';

    protected $fillable = ['establecimiento_id', 'mes', 'anio', 'serie', 'servicio_id', 'fuente'];

    protected $casts = ['mes' => 'int', 'anio' => 'int', 'servicio_id' => 'int'];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function serieRegistro()
    {
        return $this->belongsTo(Serie::class, 'serie', 'codigo');
    }
}
