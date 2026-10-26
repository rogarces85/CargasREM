<?php

namespace App\Models;

use App\Models\Concerns\ScopeServicioActivo;
use Illuminate\Database\Eloquent\Model;

/** DAT-13 datos — única tabla por serie (RF-005/RF-014; PD-PLAN-05). */
class Datos extends Model
{
    use ScopeServicioActivo;

    protected $table = 'datos';

    protected $guarded = ['id'];

    protected $casts = [
        'servicio_id' => 'int',
        'mes' => 'int',
        'anio' => 'int',
        'fecha_ingreso' => 'datetime',
        'carga_id' => 'int',
    ];

    /** col01..col50: fills masivos (precisión Decimal(15,4)). */
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

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'id');
    }
}
