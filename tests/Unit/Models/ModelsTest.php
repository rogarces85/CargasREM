<?php

namespace Tests\Unit\Models;

use App\Enums\EstadoCarga;
use App\Enums\OrigenCarga;
use App\Models\Carga;
use App\Models\CatVersion;
use App\Models\DatosCarga;
use App\Models\Establecimiento;
use App\Models\Serie;
use App\Models\Servicio;
use App\Models\Recepcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    private Servicio $servicio;
    private Serie $serieA;
    private Establecimiento $estab1;
    private Establecimiento $estab2;
    private Carga $carga1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = Servicio::create(['id' => 23, 'nombre' => 'Servicio de Salud Osorno', 'activo' => true]);
        $this->serieA = Serie::create(['codigo' => 'A', 'nombre' => 'SERIE A']);
        Serie::create(['codigo' => 'BM', 'nombre' => 'SERIE BM']);
        $this->estab1 = Establecimiento::create(['servicio_id' => 23, 'codigo_minsal' => '101001', 'nombre' => 'Estab Uno', 'activo' => true]);
        $this->estab2 = Establecimiento::create(['servicio_id' => 23, 'codigo_minsal' => '101002', 'nombre' => 'Estab Dos', 'activo' => true]);

        $this->carga1 = Carga::create([
            'servicio_id' => 23, 'serie' => 'A', 'anio' => 2026, 'mes' => 10,
            'establecimiento_id' => $this->estab1->id, 'archivo' => 'f.xls', 'origen' => 'portal', 'estado' => 'validada',
        ]);
    }

    public function test_relaciones_basicas(): void
    {
        $this->assertEquals(23, $this->estab1->servicio->id);
        $this->assertEquals('A', $this->carga1->serieRegistro->codigo);
        $this->assertEquals('101001', $this->carga1->establecimiento->codigo_minsal);
    }

    public function test_casts_enum_y_tipos(): void
    {
        $this->assertSame(EstadoCarga::Validada, $this->carga1->estado);
        $this->assertNotInstanceOf(OrigenCarga::class, null);
        $this->assertNotNull($this->carga1->created_at);
        $recepcion = Recepcion::create(['establecimiento_id' => $this->estab1->id, 'serie' => 'A', 'mes' => 10, 'anio' => 2026, 'servicio_id' => 23]);
        $this->assertIsInt($recepcion->mes);
        $this->assertIsInt($recepcion->anio);
    }

    public function test_scope_servicio_activo_filtra_por_config(): void
    {
        // FK exige existencia: servicio 30 existe pero NO es el activo (config=23)
        Servicio::create(['id' => 30, 'nombre' => 'Otro Servicio (fantasma)', 'activo' => false]);
        Carga::create(['servicio_id' => 30, 'serie' => 'A', 'anio' => 2026, 'mes' => 10,
            'establecimiento_id' => $this->estab2->id, 'archivo' => 'x.xls', 'estado' => 'pendiente']);

        $this->assertCount(1, Carga::servicioActivo()->get()); // solo servicio 23
        $this->assertCount(2, Carga::all()); // sin scope: ambas cargas (sin auth = sin aislamiento)
    }

    public function test_staging_cascade_con_carga_t016(): void
    {
        $row = new DatosCarga(['carga_id' => $this->carga1->id, 'servicio_id' => 23, 'serie' => 'A',
            'mes' => 10, 'anio' => 2026, 'establecimiento_id' => $this->estab1->id]);
        $row->save();
        $this->assertCount(1, DatosCarga::where('carga_id', $this->carga1->id)->get());

        $this->carga1->delete(); // RN-022: staging por-id borra con la carga
        $this->assertCount(0, DatosCarga::where('carga_id', $this->carga1->id)->get());
        $this->assertDatabaseCount('datos_carga', 0);
    }

    public function test_staging_cols_decimales(): void
    {
        $row = new DatosCarga(['carga_id' => $this->carga1->id, 'servicio_id' => 23, 'serie' => 'A',
            'mes' => 10, 'anio' => 2026, 'establecimiento_id' => $this->estab1->id]);
        $row->fillCols(['col01' => 87, 'col02' => null, 'col10' => 1234.5678]);
        $row->save();

        $guardado = DatosCarga::find($row->id);
        $this->assertEquals(87.0000, $guardado->col01);
        $this->assertEquals(1234.5678, $guardado->col10);
        $this->assertNull($guardado->col11);
    }

    public function test_helper_mapeo_serie_excel_rn013(): void
    {
        CatVersion::insert(['mes' => 3, 'anio' => 2026, 'version' => 'Versión 1.1:', 'serie' => 'SERIE BM1', 'serie_excel' => 'SERIE BM']);

        // serie interna per cat_versiones serie column = variante exacta ('SERIE BM1')
        $this->assertSame('SERIE BM1', Serie::buscarPorExcel('SERIE BM', 3, 2026));
        $this->assertNull(Serie::buscarPorExcel('SERIE BM', 2, 2026)); // mes distinto sin registro
    }

    public function test_mensajes_lim_en_log_error(): void
    {
        $log = \App\Models\LogError::create(['carga_id' => $this->carga1->id,
            'establecimiento_id' => $this->estab1->id, 'serie' => 'A', 'mes' => 10, 'anio' => 2026,
            'hoja' => 'A01', 'linea' => 5, 'dato' => 'abc', 'mensaje_id' => 'rem.lim.LIM-010']);
        $this->assertSame('Valor negativo o no numérico.', $log->mensaje);
    }

    public function test_feriado_enum(): void
    {
        $feriado = \App\Models\Feriado::create(['fecha' => '2026-05-28', 'tipo' => 'regional_los_lagos',
            'descripcion' => 'Naves históricas', 'anio' => 2026]);
        $this->assertSame(\App\Enums\TipoFeriado::RegionalLosLagos, $feriado->tipo);
        $this->assertEquals(1, \App\Models\Feriado::afectaLosLagos()->delAnio(2026)->count());
    }
}
