<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\Carga;
use App\Models\Establecimiento;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // T-019: roles ROL-* spatie/Shield (TEC-10)
        $this->call(ShieldRoleSeeder::class);

        // Catálogo mínimo operativo (T-025/T-026 llenan los definitivos)
        Servicio::create(['id' => 23, 'nombre' => 'Servicio de Salud Osorno', 'activo' => true]);
        foreach (['A' => 'SERIE A', 'BS' => 'SERIE BS', 'BM' => 'SERIE BM', 'D' => 'SERIE D', 'P' => 'SERIE P', 'F' => 'SERIE F'] as $cod => $nom) {
            Serie::create(['codigo' => $cod, 'nombre' => $nom]);
        }

        // Usuarios de prueba (T-029 smoke): admin, interno y cuenta establecimiento
        $estab = Establecimiento::create(['servicio_id' => 23, 'codigo_minsal' => '101123', 'nombre' => 'Hospital Base San José de Osorno', 'activo' => true]);

        User::create(['name' => 'Admin REM', 'email' => 'admin@redsaludosorno.cl', 'password' => 'cambiar-ya-2026', 'rol' => RolUsuario::Admin, 'activo' => true]); // @dev
        User::create(['name' => 'Supervisor REM', 'email' => 'supervisor@redsaludosorno.cl', 'password' => 'cambiar-ya-2026', 'rol' => RolUsuario::Supervisor, 'activo' => true]); // @dev
        User::create(['name' => 'Operador REM', 'email' => 'operador@redsaludosorno.cl', 'password' => 'cambiar-ya-2026', 'rol' => RolUsuario::Operador, 'activo' => true]); // @dev
        User::create(['name' => 'Cuenta Hospital', 'email' => 'titular.101123@saludaldia.cl', 'password' => 'cambiar-ya-2026', 'rol' => RolUsuario::Establecimiento, 'establecimiento_id' => $estab->id, 'tipo_cuenta' => 'titular', 'activo' => true]); // @dev
    }
}
