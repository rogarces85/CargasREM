<?php

namespace App\Policies;

use App\Models\Carga;
use App\Models\User;

class CargaPolicy
{
    /** Los internos ven todo; establecimiento entra al listado pero el AislamientoScope ya filtra por filas. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Carga $carga): bool
    {
        return $this->esPropio($user, $carga->establecimiento_id);
    }

    public function create(User $user): bool
    {
        return true; // interno, y establecimiento crea la propia (server fuerza su establecimiento_id)
    }

    public function update(User $user, Carga $carga): bool
    {
        return $user->rol->esInterno();
    }

    public function delete(User $user, Carga $carga): bool
    {
        return $user->rol->esInterno(); // consolidada/invalidada según reglas de negocio — el intermediario aclara por estado
    }

    public function consolidar(User $user, Carga $carga): bool
    {
        return $user->rol->esInterno(); // RF-005: solo equipo interno consolida
    }

    private function esPropio(User $user, ?int $establecimientoId): bool
    {
        if ($user->rol->esInterno()) {
            return true;
        }

        return $user->isEstablecimiento() && (int) $user->establecimiento_id === (int) $establecimientoId;
    }
}
