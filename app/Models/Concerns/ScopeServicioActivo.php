<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * RF-021 — El servicio activo es configurable (config/rem.php → 'idservicio'),
 * no fijo en Trans.mdb → SetServicio como en ValidadorRem.
 *
 * Uso: en modelos con columna `servicio_id` (cargas, datos, recepciones…).
 */
trait ScopeServicioActivo
{
    public function scopeServicioActivo(Builder $query): Builder
    {
        $idservicio = (int) config('rem.idservicio', 23);

        return $query->where($query->getModel()->getTable() . '.servicio_id', $idservicio);
    }
}
