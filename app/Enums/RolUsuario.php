<?php

namespace App\Enums;

/** Roles de usuario (SPECS §3: ROL-OPERADOR / ROL-SUPERVISOR / ROL-ADMIN / ROL-ESTABLECIMIENTO) */
enum RolUsuario: string
{
    case Operador = 'operador';
    case Supervisor = 'supervisor';
    case Admin = 'admin';
    case Establecimiento = 'establecimiento';

    /** RN-001: los roles internos ven todo; establecimiento solo su establecimiento. */
    public function esInterno(): bool
    {
        return $this !== self::Establecimiento;
    }
}
