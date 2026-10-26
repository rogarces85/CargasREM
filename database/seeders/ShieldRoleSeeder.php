<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * T-019 — 4 roles ROL-* (SPECS §3) gestionados vía Shield/spatie (modo modular).
 * ROL-ADMIN: CRUD completo de roles/permisos (12 permisos generados para Role).
 * ROL-SUPERVISOR/OPERADOR/ESTABLECIMIENTO: permisos de recursos llegan con las
 * fases C/D (T-025+) al generarse los recursos Filament.
 */
class ShieldRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'admin' => ['ViewAny:Role', 'View:Role', 'Create:Role', 'Update:Role', 'Delete:Role',
                'DeleteAny:Role', 'Restore:Role', 'RestoreAny:Role', 'ForceDelete:Role',
                'ForceDeleteAny:Role', 'Replicate:Role', 'Reorder:Role'],
            'supervisor' => ['ViewAny:Role', 'View:Role'],
            'operador' => ['ViewAny:Role', 'View:Role'],
            'establecimiento' => [],
        ];

        foreach ($roles as $nombre => $permisos) {
            $rol = Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
            $rol->syncPermissions(collect($permisos)
                ->map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web'])));
        }
    }
}
