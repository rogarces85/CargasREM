<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Establecimiento;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-022 — EnforceMfa (RN-014): en producción, ADMIN/SUPERVISOR sin TOTP
 * quedan retenidos en el enrolamiento; en dev es no-op.
 */
class EnforceMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (Servicio::query()->count() === 0) {
            Servicio::create(['id' => 23, 'nombre' => 'Servicio de Salud Osorno', 'activo' => true]);
        }
    }

    private function user(RolUsuario $rol, bool $mfaHabilitado = false, ?string $secret = null): User
    {
        $u = User::create(['name' => 'MFA ' . $rol->value,
            'email' => 'mfa-' . $rol->value . '@ssosorno.cl', 'password' => 'secret123',
            'rol' => $rol, 'activo' => true]);
        $u->forceFill(['mfa_habilitado' => $mfaHabilitado, 'mfa_secret' => $secret])->save();
        $u->refresh();

        return $u;
    }

    /** Simula producción (APP_ENV=production) en runtime. */
    private function enProducción(): void
    {
        app()->detectEnvironment(fn () => 'production');
    }

    public function test_en_dev_admin_sin_mfa_entra_normal(): void
    {
        $admin = $this->user(RolUsuario::Admin); // sin enrolar

        $this->actingAs($admin)->get('/backoffice')
            ->assertStatus(200); // dev: RN-014 opcional
    }

    public function test_en_produccion_admin_sin_enrolar_es_retenido_en_mfa(): void
    {
        $this->enProducción();
        $admin = $this->user(RolUsuario::Admin);

        $this->actingAs($admin)->get('/backoffice')
            ->assertRedirect(route('mfa.setup'));
    }

    public function test_en_produccion_admin_enrolado_navega_libre(): void
    {
        $this->enProducción();
        $admin = $this->user(RolUsuario::Admin, mfaHabilitado: true, secret: 'KRSXG5DSNFVA====');

        $this->actingAs($admin)->get('/backoffice')
            ->assertStatus(200);
    }

    public function test_en_produccion_establecimiento_no_requiere_mfa(): void
    {
        $this->enProducción();
        $estab = Establecimiento::create(['servicio_id' => 23, 'codigo_minsal' => '101001', 'nombre' => 'Estab MFA', 'activo' => true]);
        $u = $this->user(RolUsuario::Establecimiento);
        $u->forceFill(['establecimiento_id' => $estab->id])->save();

        $this->actingAs($u)->get('/portal')
            ->assertStatus(200); // RN-014: MFA solo obligatorio Admin/Supervisor
    }

    public function test_en_produccion_supervisor_con_mfa_flag_pero_sin_secret_tmb_retenido(): void
    {
        $this->enProducción();
        $sup = $this->user(RolUsuario::Supervisor, mfaHabilitado: true); // flag sin secreto

        $this->actingAs($sup)->get('/backoffice')
            ->assertRedirect(route('mfa.setup'));
    }

    public function test_ruta_de_enrolamiento_alcanzable_sin_loop(): void
    {
        $this->enProducción();
        $admin = $this->user(RolUsuario::Admin); // sin enrolar

        // la propia página de enrolamiento debe ser accesible (permitida por el middleware)
        $this->actingAs($admin)->get('/mfa')->assertStatus(200);
    }
}
