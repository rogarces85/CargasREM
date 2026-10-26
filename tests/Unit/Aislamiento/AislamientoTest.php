<?php

namespace Tests\Unit\Aislamiento;

use App\Enums\RolUsuario;
use App\Models\Carga;
use App\Models\Establecimiento;
use App\Models\Recepcion;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-018 — RN-001 (aislamiento por establecimiento) + policies base.
 * Hecho cuando: un user de establecimiento A no resuelve modelos del B (0 filas).
 */
class AislamientoTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $estab1;
    private Establecimiento $estab2;
    private User $userEstab1;
    private User $userEstab2;
    private User $userInterno;
    private Carga $carga1;
    private Carga $carga2;
    private Recepcion $recepcion2;

    protected function setUp(): void
    {
        parent::setUp();

        Servicio::create(['id' => 23, 'nombre' => 'Servicio de Salud Osorno', 'activo' => true]);
        Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);

        $this->estab1 = Establecimiento::create(['servicio_id' => 23, 'codigo_minsal' => '101001', 'nombre' => 'Estab Uno', 'activo' => true]);
        $this->estab2 = Establecimiento::create(['servicio_id' => 23, 'codigo_minsal' => '101002', 'nombre' => 'Estab Dos', 'activo' => true]);

        $this->userEstab1 = User::create(['name' => 'Op Uno', 'email' => 'estab1@ssosorno.cl', 'password' => 'secret123',
            'rol' => RolUsuario::Establecimiento, 'establecimiento_id' => $this->estab1->id, 'activo' => true]);
        $this->userEstab2 = User::create(['name' => 'Op Dos', 'email' => 'estab2@ssosorno.cl', 'password' => 'secret123',
            'rol' => RolUsuario::Establecimiento, 'establecimiento_id' => $this->estab2->id, 'activo' => true]);
        $this->userInterno = User::create(['name' => 'Supervisor REM', 'email' => 'supervisor@ssosorno.cl', 'password' => 'secret123',
            'rol' => RolUsuario::Supervisor, 'establecimiento_id' => null, 'activo' => true]);

        $this->carga1 = Carga::create(['servicio_id' => 23, 'serie' => 'A', 'anio' => 2026, 'mes' => 10,
            'establecimiento_id' => $this->estab1->id, 'archivo' => 'uno.xlsx', 'origen' => 'portal', 'estado' => 'validada']);
        $this->carga2 = Carga::create(['servicio_id' => 23, 'serie' => 'A', 'anio' => 2026, 'mes' => 10,
            'establecimiento_id' => $this->estab2->id, 'archivo' => 'dos.xlsx', 'origen' => 'portal', 'estado' => 'pendiente']);

        $this->recepcion2 = Recepcion::create(['establecimiento_id' => $this->estab2->id, 'serie' => 'A',
            'mes' => 10, 'anio' => 2026, 'servicio_id' => 23]);
    }

    // ---- RN-001: global scope ----

    public function test_establecimiento_a_no_ve_cargas_del_b(): void
    {
        $this->actingAs($this->userEstab1);

        $cargas = Carga::all();

        $this->assertCount(1, $cargas);                       // solo la propia
        $this->assertTrue($cargas->contains($this->carga1));
        $this->assertSame(0, Carga::where('establecimiento_id', $this->estab2->id)->count());
        $this->assertFalse($cargas->contains($this->carga2)); // 0 filas del B
    }

    public function test_establecimiento_b_no_ve_recepcion_del_a(): void
    {
        $this->actingAs($this->userEstab2);

        $recepciones = Recepcion::all();

        $this->assertCount(1, $recepciones);
        $this->assertTrue($recepciones->contains($this->recepcion2));
        $this->assertSame(0, Recepcion::where('establecimiento_id', $this->estab1->id)->count());
    }

    public function test_interno_ve_todo_el_universo(): void
    {
        $this->actingAs($this->userInterno);

        $this->assertCount(2, Carga::all());
        $this->assertCount(1, Recepcion::all());
    }

    public function test_sin_auth_no_filtra_worker_cli(): void
    {
        // sin usuario autenticado (worker/CLI): sin filtro RN-001
        $this->assertNull(auth()->user());
        $this->assertCount(2, Carga::all());
    }

    // ---- Policies (MOD-16 / CA-SEG-01) ----

    public function test_policy_view_carga_restringe_ajeno(): void
    {
        $this->assertFalse($this->userEstab1->can('view', $this->carga2));
        $this->assertTrue($this->userEstab1->can('view', $this->carga1));
        $this->assertTrue($this->userEstab2->can('view', $this->carga2));
        $this->assertTrue($this->userInterno->can('view', $this->carga2));
    }

    public function test_policy_view_any_abierto_con_scope_por_filas(): void
    {
        $this->assertTrue($this->userEstab1->can('viewAny', Carga::class));
        $this->assertFalse($this->userEstab1->can('consolidar', $this->carga2)); // solo interno (RF-005)
        $this->assertTrue($this->userInterno->can('consolidar', $this->carga2));
    }

    public function test_policy_recepcion_subir_version_solo_propio(): void
    {
        $recepcion1 = Recepcion::create(['establecimiento_id' => $this->estab1->id, 'serie' => 'A',
            'mes' => 9, 'anio' => 2026, 'servicio_id' => 23]);

        $this->assertTrue($this->userEstab1->can('subirVersion', $recepcion1));
        $this->assertFalse($this->userEstab2->can('subirVersion', $recepcion1));
        $this->assertFalse($this->userInterno->can('subirVersion', $this->recepcion2)); // interno observa, no sube

        $this->assertTrue($this->userInterno->can('observar', $this->recepcion2));
        $this->assertFalse($this->userEstab1->can('observar', $this->recepcion2));

        $this->assertTrue($this->userEstab2->can('view', $this->recepcion2));
        $this->assertFalse($this->userEstab1->can('view', $this->recepcion2));
        $this->assertTrue($this->userInterno->can('view', $this->recepcion2));
    }

    public function test_policy_establecimientos_y_usuarios_propios(): void
    {
        $this->assertTrue($this->userEstab1->can('view', $this->estab1));
        $this->assertFalse($this->userEstab1->can('view', $this->estab2));
        $this->assertTrue($this->userInterno->can('viewAny', Establecimiento::class));
        $this->assertFalse($this->userEstab1->can('viewAny', Establecimiento::class));
        $this->assertFalse($this->userInterno->can('create', Establecimiento::class)); // solo admin

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@ssosorno.cl', 'password' => 'secret123',
            'rol' => RolUsuario::Admin, 'establecimiento_id' => null, 'activo' => true]);
        $this->assertTrue($admin->can('create', Establecimiento::class));
    }
}
