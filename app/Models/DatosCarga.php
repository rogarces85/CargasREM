<?php

namespace App\Models;

use App\Models\Concerns\ScopeServicioActivo;
use Illuminate\Database\Eloquent\Model;

/**
 * T-016 staging (RF-003 → RF-005): transitorias; el consolidador (T-046) hace
 * DELETE+INSERT a datos/datos_control (RN-021/RN-022) y purga por carga_id.
 */
class DatosCarga extends Model
{
    use ScopeServicioActivo;

    public $timestamps = false;

    protected $table = 'datos_carga';

    protected $guarded = ['id'];

    protected $casts = [
        'carga_id' => 'int',
        'servicio_id' => 'int',
        'mes' => 'int',
        'anio' => 'int',
        'linea' => 'int',
        'fecha_ingreso' => 'datetime',
    ];

    public function carga()
    {
        return $this->belongsTo(Carga::class);
    }

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function serieRegistro()
    {
        return $this->belongsTo(Serie::class, 'serie', 'codigo');
    }

    public function fillCols(array $valores): static
    {
        $cols = [];
        foreach ($valores as $n => $v) {
            if (preg_match('/^col\d{2}$/i', (string) $n)) {
                $cols[strtolower($n)] = $v;
            }
        }
        if ($cols !== []) {
            $this->fill($cols);
        }

        return $this;
    }
}
