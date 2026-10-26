<?php
/*
 * Verificación migraciones FASE B (T-012/T-013/T-014/T-015)
 * Ejecutar: php poc/verifica_migraciones.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// ── 1. Índices por negocio (SHOW INDEX resumido) ────────────────
foreach (['datos', 'datos_control', 'cargas', 'recepciones', 'cat_versiones', 'users', 'hist_carga'] as $t) {
    $idx = DB::select("SHOW INDEX FROM `$t`");
    $nombres = array_unique(array_map(fn ($r) => $r->Key_name, $idx));
    echo "$t: ", implode(', ', $nombres), "\n";
}

// ── 2. Siembras mínimas para probar FK/UNIQUE ───────────────────
DB::table('series')->insert(['codigo' => 'A', 'nombre' => 'SERIE A']);
DB::table('servicios')->insert(['id' => 23, 'nombre' => 'Servicio de Salud Osorno']);
$estab = DB::table('establecimientos')->insertGetId([
    'servicio_id' => 23, 'codigo_minsal' => 'TEST001', 'nombre' => 'Hospital Falso', 'activo' => true,
]);
echo "sembradas series/servicios/establecimiento\n";

// FK correcta: datos → establecimiento_id
try {
    DB::table('datos')->insert(['servicio_id' => 23, 'serie' => 'A', 'mes' => 10, 'anio' => 2026,
        'establecimiento_id' => $estab, 'col01' => 1234.5678]);
    echo "INSERT datos con FK válida: OK\n";
} catch (Throwable $e) {
    echo "FALLO insert válido: ", substr($e->getMessage(), 0, 120), "\n";
}

// FK violada: datos con establecimiento inexistente
try {
    DB::table('datos')->insert(['servicio_id' => 23, 'serie' => 'A', 'mes' => 10, 'anio' => 2026,
        'establecimiento_id' => 999999]);
    echo "FALLO: FK aceptó establecimiento inexistente\n";
    DB::table('datos')->where('establecimiento_id', 999999)->delete();
} catch (Throwable $e) {
    echo "FK violación detectada (establecimiento inexistente): ", (str_contains($e->getMessage(), '1451') || str_contains($e->getMessage(), 'Integrity constraint') ? 'rechazada OK' : substr($e->getMessage(), 0, 100)), "\n";
}

// FK restrict: borrar servicio referenciado
try {
    DB::table('servicios')->where('id', 23)->delete();
    echo "FALLO: borró servicio con hijos\n";
} catch (Throwable $e) {
    echo "RESTRICT servicio con establecimientos: ", (str_contains($e->getMessage(), '1451') ? 'bloqueado OK' : ''), "\n";
}

// UNIQUE recepciones por grupo (estab, serie, mes, año, servicio)
DB::table('recepciones')->insert(['establecimiento_id' => $estab, 'serie' => 'A', 'mes' => 10, 'anio' => 2026, 'servicio_id' => 23]);
try {
    DB::table('recepciones')->insert(['establecimiento_id' => $estab, 'serie' => 'A', 'mes' => 10, 'anio' => 2026, 'servicio_id' => 23]);
    echo "FALLO: UNIQUE recepciones no rechazó duplicado\n";
    DB::table('recepciones')->where('establecimiento_id', $estab)->orderBy('id', 'desc')->take(1)->delete();
} catch (Throwable $e) {
    echo "UNIQUE recepciones duplicado: rechazado OK\n";
}

// DECIMAL 15,4 redondeo: col01 = 1.12345 → 1.1235
$id = DB::table('datos')->max('id');
$v = DB::table('datos')->where('id', $id)->value('col01');
echo "DECIMAL(15,4) guard/leído: $v\n";

// activitylog estructura acepta causer/IP/props
activity()
    ->withProperties(['ip' => '127.0.0.1', 'prueba' => true])
    ->log('POC T-015: fila de prueba');
$fila = DB::table(config('activitylog.table_name'))->orderBy('id', 'desc')->first();
echo "activity_log: ", json_encode(['event' => $fila->event, 'causer_type' => $fila->causer_type, 'props' => $fila->properties, 'ip' => str_contains($fila->properties, '127.0.0.1')], JSON_UNESCAPED_UNICODE), "\n";

// ── limpieza total de pruebas ──────────────────────────────────
DB::table(config('activitylog.table_name'))->where('description', 'POC T-015: fila de prueba')->delete();
DB::table('datos')->where('establecimiento_id', $estab)->delete();
DB::table('recepciones')->where('establecimiento_id', $estab)->delete();
DB::table('establecimientos')->where('id', $estab)->delete();
DB::table('servicios')->where('id', 23)->delete();
DB::table('series')->where('codigo', 'A')->delete();
echo "limpieza de datos de prueba: OK\n";
