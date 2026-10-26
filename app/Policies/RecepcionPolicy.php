<?php

namespace App\Policies;

use App\Models\Recepcion;
use App\Models\User;

class RecepcionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recepcion $recepcion): bool
    {
        if ($user->rol->esInterno()) {
            return true;
        }

        return $user->isEstablecimiento() && (int) $user->establecimiento_id === (int) $recepcion->establecimiento_id;
    }

    /** RN-011/RN-012 – solo cuenta controla sube nueva versión (T-9 portal). */
    public function subirVersion(User $user, Recepcion $recepcion): bool
    {
        return $user->isEstablecimiento() && (int) $user->establecimiento_id === (int) $recepcion->establecimiento_id;
    }

    /** RF-031 (observar/rechazar) — interno. */
    public function observar(User $user, Recepcion $recepcion): bool
    {
        return $user->rol->esInterno();
    }
}
