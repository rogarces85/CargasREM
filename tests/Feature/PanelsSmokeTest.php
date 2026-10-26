<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Establecimiento;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * T-020/T-021/T-019 — Hecho cuando:
 * - login admin → panel /backoffice visible;
 * - login cuenta establecimiento → /portal visible;
 * - acceso cruzado bloqueado; 4 roles ROL-* gestionables.
 */
class PanelsSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $estab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ShieldRoleSeeder::class);

        Servicio::create(['id' => 23, 'nombre' => 'Servicio de Salud Osorno', 'activo' => true]);
        $this->estab = Establecimiento::create(['servicio_id' => 23, 'codigo_minsal' => '101001',
            'nombre' => 'Estab Uno', 'activo' => true]);
    }

    private function user(RolUsuario $rol, ?int $estabId = null): User
    {
        return User::create(['name' => 'Smoke ' . $rol->value,
            'email' => $rol->value . '-smoke@ssosorno.cl', 'password' => 'secret123',
            'rol' => $rol, 'establecimiento_id' => $estabId, 'activo' => true]);
    }

    public function test_admin_ve_dashboard_backoffice(): void
    {
        $admin = $this->user(RolUsuario::Admin);

        $this->actingAs($admin)->get('/backoffice')
            ->assertStatus(200)
            ->assertSee('WebCargaREM');
    }

    public function test_grupos_navegacion_rn023_visibles_en_backoffice(): void
    {
        $admin = $this->user(RolUsuario::Admin);

        $this->actingAs($admin)->get('/backoffice/carga') // placeholder del grupo Carga
            ->assertStatus(200)
            ->assertSee('Carga')
            ->assertSee('Informes')
            ->assertSee('Minsal')
            ->assertSee('Administración')
            ->assertSee('Recepción');
    }

    public function test_establecimiento_no_entra_al_backoffice(): void    {
        $estab = $this->user(RolUsuario::Establecimiento);

        $this->actingAs($estab)->get('/backoffice')
            ->assertStatus(403); // Filament niega el panel (canAccessPanel false)
    }

    public function test_establecimiento_ve_dashboard_portal(): void
    {
        $estab = $this->user(RolUsuario::Establecimiento, $this->estab->id);

        // Sidebar placeholder (SPECS §7.2): Calendario/Cargar/Historial/Cumplimiento
        $this->actingAs($estab)->get('/portal')
            ->assertStatus(200)
            ->assertSee('S.S. Osorno')
            ->assertSee('Calendario')
            ->assertSee('Historial')
            ->assertSee('Cumplimiento');
    }

    public function test_interno_no_entra_al_portal(): void
    {
        $op = $this->user(RolUsuario::Operador);

        $this->actingAs($op)->get('/portal')
            ->assertStatus(403);
    }

    public function test_cuenta_inactiva_no_accede_a_ningun_panel(): void
    {
        $inactivo = User::create(['name' => 'X', 'email' => 'x@ssosorno.cl', 'password' => 'secret123',
            'rol' => RolUsuario::Admin, 'activo' => false]);

        $this->actingAs($inactivo)->get('/backoffice')->assertStatus(403);
        $this->actingAs($inactivo)->get('/portal')->assertStatus(403);
    }

    public function test_anonimo_redirige_a_login(): void
    {
        $this->get('/backoffice')->assertRedirect();
        $this->get('/portal')->assertRedirect();
    }

    public function test_cuatro_roles_rol_star_existentes(): void
    {
        foreach (['admin', 'supervisor', 'operador', 'establecimiento'] as $nombre) {
            $this->assertTrue(Role::where('name', $nombre)->exists(), "falta rol {$nombre}");
        }
    }

    public function test_rol_dominio_se_refleja_como_rol_spatie(): void
    {
        $admin = $this->user(RolUsuario::Admin);

        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->can('ViewAny:Role')); // Shield: gestion de permisos vía panel

        $op = $this->user(RolUsuario::Operador);
        $this->assertTrue($op->hasRole('operador'));
        $this->assertFalse($op->can('Update:Role')); // operador gestiona carga, no roles
    }
}
