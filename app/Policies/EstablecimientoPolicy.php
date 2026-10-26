<?php

namespace App\Policies;

use App\Models\Establecimiento;
use App\Models\User;

class EstablecimientoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->rol->esInterno();
    }

    public function view(User $user, Establecimiento $establecimiento): bool
    {
        if ($user->rol->esInterno()) {
            return true;
        }

        return $user->isEstablecimiento() && (int) $user->establecimiento_id === (int) $establecimiento->id;
    }

    public function create(User $user): bool
    {
        return $user->rol === \App\Enums\RolUsuario::Admin;
    }

    public function update(User $user, Establecimiento $establecimiento): bool
    {
        return $user->rol === \App\Enums\RolUsuario::Admin;
    }

    public function delete(User $user, Establecimiento $establecimiento): bool
    {
        return $user->rol === \App\Enums\RolUsuario::Admin;
    }
}
