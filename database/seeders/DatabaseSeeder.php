<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\Establecimiento;
use App\Models\Serie;
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

        // T-025: catálogos completos (o base mínima RN-031 si no hay .mdb en el servidor)
        $this->call(CatalogosSeeder::class);

        // T-026: feriados 2025–2027 (RN-042)
        $this->call(FeriadosSeeder::class);

        // Series de trabajo (nomenclador de la app; cat_versiones mantiene el mapeo Excel ↔ serie)
        foreach (['A' => 'SERIE A', 'BS' => 'SERIE BS', 'BM' => 'SERIE BM', 'D' => 'SERIE D', 'P' => 'SERIE P', 'F' => 'SERIE F'] as $cod => $nom) {
            Serie::updateOrCreate(['codigo' => $cod], ['nombre' => $nom]);
        }

        // Usuarios de prueba (T-024: password vía .env → REM_ADMIN_PASSWORD; nunca hardcode en prod)
        $pass = env('REM_ADMIN_PASSWORD', 'cambiar-ya-2026'); // @dev default
        // firstOrCreate: si CatalogosSeeder importó 101123 desde el mdb ya existe (unq_estab_servicio_codigo)
        $estab = Establecimiento::firstOrCreate(
            ['servicio_id' => 23, 'codigo_minsal' => '101123'],
            ['nombre' => 'Hospital Base San José de Osorno', 'activo' => true],
        );

        User::updateOrCreate(['email' => 'admin@redsaludosorno.cl'], ['name' => 'Admin REM', 'password' => $pass, 'rol' => RolUsuario::Admin, 'activo' => true]);
        User::updateOrCreate(['email' => 'supervisor@redsaludosorno.cl'], ['name' => 'Supervisor REM', 'password' => $pass, 'rol' => RolUsuario::Supervisor, 'activo' => true]);
        User::updateOrCreate(['email' => 'operador@redsaludosorno.cl'], ['name' => 'Operador REM', 'password' => $pass, 'rol' => RolUsuario::Operador, 'activo' => true]);
        User::updateOrCreate(['email' => 'titular.101123@saludaldia.cl'], ['name' => 'Cuenta Hospital', 'password' => $pass, 'rol' => RolUsuario::Establecimiento, 'establecimiento_id' => $estab->id, 'tipo_cuenta' => 'titular', 'activo' => true]);
    }
}
