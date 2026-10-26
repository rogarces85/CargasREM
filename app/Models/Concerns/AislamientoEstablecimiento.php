<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * RN-001 — Un ROL-ESTABLECIMIENTO solo ve y opera (por consultas) datos de su
 * establecimiento. Los roles internos (operador/supervisor/admin) ven todo.
 * El filtro solo actúa con sesión activa: en worker/CLI no hay usuario
 * autenticado y el job debe ver el universo completo.
 */
trait AislamientoEstablecimiento
{
    public static function bootAislamientoEstablecimiento(): void
    {
        static::addGlobalScope('aislamiento_establecimiento', function (Builder $query) {
            $user = auth()->user();

            if (! $user || ! $user->isEstablecimiento()) {
                return;
            }

            $query->where($query->getModel()->getTable() . '.establecimiento_id', $user->establecimiento_id);
        });
    }
}
