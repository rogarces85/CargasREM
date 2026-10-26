<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * T-025 — Catálogos desde BD/Global.mdb vía COM/ACE (mismo mecanismo del POC T-005;
 * RN-031 lo mantiene como fallback cuando la sync Minsal vía HTTP no esté disponible).
 *
 * Mapeos (introspección con OpenSchema):
 *  mpServicio        → servicios        (IdServicio, NServicio, IdRegion, Orden, ElimID)
 *  mpEstablecimiento → establecimientos (solo servicio activo 23, ElimID=0)
 *  Diccionario       → cat_diccionario
 *  Thojas            → cat_thojas       (mes VARCHAR '' → NULL)
 *  Excep             → cat_excep        (colexcep VARCHAR '1,7' → col_excep)
 *  colexcep          → cat_colexcep     (codpres, col)
 *  version           → cat_versiones    (insertOrIgnore: UNIQUE serie_excel+anio+mes)
 *  Software          → cat_software
 *
 * Textos en ANSI (CP1252) se convierten a UTF-8 ('Versión 1.2:', 'ñ').
 * Si el .mdb no está disponible (servidor) imprime un resumen 'fallback' y
 * garantiza serie de servicio 23 (base mínima para operar).
 */
class CatalogosSeeder extends Seeder
{
    private const MDB = 'C:/xampp/htdocs/www/CargasREM/BD/Global.mdb';

    public function run(): void
    {
        ini_set('memory_limit', '1024M');

        $resumen = [];

        // Guard idempotencia: los catálogos del mdb se cargan sobre tablas vacías
        // (flujos válidos: migrate:fresh --seed, o primera carga RN-031). Re-ejecutar
        // sin fresh duplicaría cat_diccionario (no tiene UNIQUE) — avisamos y salimos.
        if (DB::table('cat_thojas')->count() > 0 && DB::table('cat_diccionario')->count() > 0) {
            $this->command?->warn('T-025: catálogos ya poblados — nada por hacer (use migrate:fresh --seed para recargar desde el mdb).');

            return;
        }

        $conn = $this->abrirMdb();

        if ($conn === null) {
            $this->fallback($resumen);
            $this->imprimir($resumen, true);

            return;
        }

        // ── servicios (30; insertOrIgnore coexistente con el primer-admin del DatabaseSeeder)
        $n = 0;
        $t = $conn->Execute('SELECT IdServicio, NServicio, IdRegion, Orden, ElimID FROM mpServicio');
        $filas = [];
        while (! $t->EOF) {
            $id = (int) $t->Fields->Item('IdServicio')->Value;
            $filas[] = [
                'id' => $id,
                'nombre' => $this->txt($t->Fields->Item('NServicio')->Value),
                'region' => $t->Fields->Item('IdRegion')->Value !== null ? (int) $t->Fields->Item('IdRegion')->Value : null,
                'orden' => $t->Fields->Item('Orden')->Value !== null ? (int) $t->Fields->Item('Orden')->Value : null,
                'activo' => (string) $t->Fields->Item('ElimID')->Value === '0',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $t->MoveNext();
        }
        $t->Close();
        $n = $this->flush('servicios', $filas);
        $resumen['servicios'] = ['mdb' => count($filas), 'mysql' => DB::table('servicios')->count(),
            'nota' => $this->command && $n !== count($filas) ? "insertadas {$n} (insertOrIgnore)" : null];

        // ── establecimientos del servicio activo (RN-010: la app opera servicio 23)
        $t = $conn->Execute('SELECT IdEstablecimiento, NEstablecimiento, Direccion, Telefono, IdComuna, IdDependencia, IdTipo, Propiedad, ElimID FROM mpEstablecimiento WHERE IdServicio = 23 AND ElimID = 0');
        $filas = [];
        while (! $t->EOF) {
            $idtipo = $t->Fields->Item('IdTipo')->Value;
            $filas[] = [
                'servicio_id' => 23,
                'codigo_minsal' => (string) $t->Fields->Item('IdEstablecimiento')->Value,
                'nombre' => $this->txt($t->Fields->Item('NEstablecimiento')->Value),
                'direccion' => $this->txt($t->Fields->Item('Direccion')->Value),
                'telefono' => $this->txt($t->Fields->Item('Telefono')->Value),
                'idcomuna' => $t->Fields->Item('IdComuna')->Value !== null ? (int) $t->Fields->Item('IdComuna')->Value : null,
                'iddependencia' => $t->Fields->Item('IdDependencia')->Value !== null ? (int) $t->Fields->Item('IdDependencia')->Value : null,
                'idtipo' => (filled($idtipo) && ctype_digit((string) $idtipo)) ? (int) $idtipo : null,
                'propiedad' => $this->txt($t->Fields->Item('Propiedad')->Value),
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $t->MoveNext();
        }
        $t->Close();
        $n = $this->flush('establecimientos', $filas);
        $resumen['establecimientos'] = ['mdb' => count($filas),
            'mysql' => DB::table('establecimientos')->where('servicio_id', 23)->count(),
            'nota' => $this->command && $n !== count($filas) ? "insertadas {$n} (insertOrIgnore)" : null];

        // ── cat_diccionario (185 performance-k) — batch 2000
        $t = $conn->Execute('SELECT CodigoPrestacion, Mes, Year, TextoPrestacion, Hoja, Serie, Posicion, tipoDato, linea, Inicio, Fin FROM Diccionario');
        $filas = [];
        $saltadas = 0;
        while (! $t->EOF) {
            $hoja = trim((string) $t->Fields->Item('Hoja')->Value);
            if (mb_strlen($hoja) > 5) {
                $saltadas++;
                $t->MoveNext();
                continue;
            }
            $mes = trim((string) $t->Fields->Item('Mes')->Value);
            $posicion = trim((string) $t->Fields->Item('Posicion')->Value);
            $filas[] = [
                'codigo_prestacion' => $this->txt($t->Fields->Item('CodigoPrestacion')->Value),
                'mes' => (filled($mes) && ctype_digit($mes)) ? (int) $mes : 0,
                'anio' => (int) $t->Fields->Item('Year')->Value,
                'texto_prestacion' => $this->txt($t->Fields->Item('TextoPrestacion')->Value),
                'hoja' => $hoja,
                'serie' => $this->txt($t->Fields->Item('Serie')->Value),
                'posicion' => (filled($posicion) && ctype_digit($posicion)) ? (int) $posicion : 0,
                'tipo_dato' => $this->txt($t->Fields->Item('tipoDato')->Value),
                'linea' => $t->Fields->Item('linea')->Value !== null ? (int) $t->Fields->Item('linea')->Value : null,
                'inicio' => $t->Fields->Item('Inicio')->Value !== null ? (int) $t->Fields->Item('Inicio')->Value : null,
                'fin' => $t->Fields->Item('Fin')->Value !== null ? (int) $t->Fields->Item('Fin')->Value : null,
            ];
            if (count($filas) >= 2000) {
                $this->flush('cat_diccionario', $filas);
                $filas = [];
            }
            $t->MoveNext();
        }
        if ($filas !== []) {
            $this->flush('cat_diccionario', $filas);
        }
        $t->Close();
        $resumen['cat_diccionario'] = ['mdb' => $this->contar($conn, 'Diccionario'),
            'mysql' => DB::table('cat_diccionario')->count(), 'saltadas_hoja_larga' => $saltadas];

        // ── cat_thojas (1129) — mes '' → NULL
        $t = $conn->Execute('SELECT Serie, Hoja, ano, mes, Rango FROM Thojas');
        $filas = [];
        while (! $t->EOF) {
            $mes = trim((string) $t->Fields->Item('mes')->Value);
            $filas[] = [
                'serie' => $this->txt($t->Fields->Item('Serie')->Value),
                'hoja' => $this->txt($t->Fields->Item('Hoja')->Value),
                'anio' => (int) $t->Fields->Item('ano')->Value,
                'mes' => (filled($mes) && ctype_digit($mes)) ? (int) $mes : null,
                'rango' => $this->txt($t->Fields->Item('Rango')->Value),
            ];
            $t->MoveNext();
        }
        $t->Close();
        $resumen['cat_thojas'] = ['mdb' => count($filas), 'mysql' => $this->flush('cat_thojas', $filas)];

        // ── cat_excep (5664) — colexcep VARCHAR a col_excep
        $t = $conn->Execute('SELECT CodigoPrestacion, Mes, Year, Hoja, Serie, Posicion, tipoDato, colexcep FROM Excep');
        $filas = [];
        while (! $t->EOF) {
            $mes = trim((string) $t->Fields->Item('Mes')->Value);
            $pos = trim((string) $t->Fields->Item('Posicion')->Value);
            $filas[] = [
                'codigo_prestacion' => $this->txt($t->Fields->Item('CodigoPrestacion')->Value),
                'mes' => (filled($mes) && ctype_digit($mes)) ? (int) $mes : 0,
                'anio' => (int) $t->Fields->Item('Year')->Value,
                'hoja' => $this->txt($t->Fields->Item('Hoja')->Value),
                'serie' => $this->txt($t->Fields->Item('Serie')->Value),
                'posicion' => (filled($pos) && ctype_digit($pos)) ? (int) $pos : null,
                'tipo_dato' => $this->txt($t->Fields->Item('tipoDato')->Value),
                'col_excep' => $this->txt($t->Fields->Item('colexcep')->Value),
            ];
            $t->MoveNext();
        }
        $t->Close();
        $resumen['cat_excep'] = ['mdb' => count($filas), 'mysql' => $this->flush('cat_excep', $filas)];

        // ── cat_colexcep (2977)
        $t = $conn->Execute('SELECT codpres, col FROM colexcep');
        $filas = [];
        while (! $t->EOF) {
            $filas[] = [
                'codigo_pres' => $this->txt($t->Fields->Item('codpres')->Value),
                'col' => (int) $t->Fields->Item('col')->Value,
            ];
            $t->MoveNext();
        }
        $t->Close();
        $resumen['cat_colexcep'] = ['mdb' => count($filas), 'mysql' => $this->flush('cat_colexcep', $filas)];

        // ── cat_versiones (939; UNIQUE → insertOrIgnore e informe de duplicados)
        $t = $conn->Execute('SELECT mes, ano, version, Serie, SerieExcel FROM [version]');
        $filas = [];
        $duplicados = 0;
        while (! $t->EOF) {
            $fila = [
                'mes' => (int) $t->Fields->Item('mes')->Value,
                'anio' => (int) $t->Fields->Item('ano')->Value,
                'version' => $this->txt($t->Fields->Item('version')->Value),
                'serie' => $this->txt($t->Fields->Item('Serie')->Value),
                'serie_excel' => $this->txt($t->Fields->Item('SerieExcel')->Value),
            ];
            $existe = DB::table('cat_versiones')->where([
                'serie_excel' => $fila['serie_excel'], 'anio' => $fila['anio'], 'mes' => $fila['mes'],
            ])->exists();
            if ($existe) {
                $duplicados++;
            } else {
                $filas[] = $fila;
            }
            $t->MoveNext();
        }
        $t->Close();
        $n = $this->flush('cat_versiones', $filas);
        $resumen['cat_versiones'] = ['mdb' => $this->contar($conn, 'version'),
            'mysql' => DB::table('cat_versiones')->count(), 'duplicados_mdb' => $duplicados + (count($filas) - $n)];

        // ── cat_software (1)
        $t = $conn->Execute('SELECT Nombre, Version FROM Software');
        $filas = [];
        while (! $t->EOF) {
            $fila = ['nombre' => $this->txt($t->Fields->Item('Nombre')->Value),
                'version' => $this->txt($t->Fields->Item('Version')->Value)];
            DB::table('cat_software')->updateOrInsert(['nombre' => $fila['nombre']], $fila);
            $filas[] = $fila;
            $t->MoveNext();
        }
        $t->Close();
        $resumen['cat_software'] = ['mdb' => 1, 'mysql' => DB::table('cat_software')->count()];

        $conn->Close();
        $this->imprimir($resumen, false);
    }

    /** ADO connection modo lectura (RN-032/TEC-12: el original queda intocado). */
    private function abrirMdb(): ?object
    {
        if (! file_exists(self::MDB)) {
            return null;
        }

        try {
            $conn = new \COM('ADODB.Connection');
            $conn->Mode = 1; // adModeRead
            $conn->Open('Provider=Microsoft.ACE.OLEDB.12.0;Data Source=' . self::MDB . ';Persist Security Info=False;');

            return $conn;
        } catch (\Throwable $e) {
            $this->command?->warn('CAT seeder fallback: ' . $e->getMessage());

            return null;
        }
    }

    /** Flush en lotes dentro de transacción; devuelve total insertado. */
    private function flush(string $tabla, array $filas): int
    {
        if ($filas === []) {
            return 0;
        }

        $total = 0;
        $marcas = ['created_at' => now(), 'updated_at' => now()];
        foreach (array_chunk($filas, 1000) as $chunk) {
            DB::transaction(function () use ($tabla, $chunk, $marcas, &$total) {
                foreach ($chunk as &$f) {
                    if ($tabla === 'servicios' || $tabla === 'establecimientos') {
                        unset($f['created_at'], $f['updated_at']);
                        $f += $marcas;
                    }
                }

                $total += DB::table($tabla)->insertOrIgnore($chunk);
            });
        }

        return (int) $total;
    }

    private function contar(object $conn, string $tabla): int
    {
        $r = $conn->Execute('SELECT COUNT(*) AS c FROM [' . $tabla . ']');
        $c = (int) $r->Fields->Item('c')->Value;
        $r->Close();

        return $c;
    }

    /** ANSI (CP1252) → UTF-8. */
    private function txt(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }

        if (! mb_check_encoding($s, 'UTF-8')) {
            $s = iconv('Windows-1252', 'UTF-8//IGNORE', $s) ?: $s;
        }

        return $s;
    }

    /** Fallback RN-031: base mínima operativa si el .mdb no está en el servidor. */
    private function fallback(array &$resumen): void
    {
        DB::table('servicios')->insertOrIgnore([
            'id' => 23, 'nombre' => 'Servicio de Salud Osorno', 'activo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $resumen['_fallback'] = 'BD/Global.mdb no disponible en este entorno: se garantiza servicio 23 y series base; catálogos completos llegan por sync Minsal (T-075).';
    }

    private function imprimir(array $resumen, bool $fallback): void
    {
        echo "=== T-025 resumen catálogos ===", PHP_EOL;
        foreach ($resumen as $tabla => $v) {
            if (is_string($v)) {
                echo "  {$tabla}: {$v}", PHP_EOL;
                continue;
            }
            $ok = ($v['mdb'] === $v['mysql']) ? 'MATCH' : 'Δ';
            echo sprintf("  %-18s mdb=%-7d mysql=%-7d %s%s%s", $tabla, $v['mdb'], $v['mysql'], $ok,
                isset($v['duplicados_mdb']) && $v['duplicados_mdb'] > 0 ? " (dupes={$v['duplicados_mdb']})" : '',
                isset($v['saltadas_hoja_larga']) && $v['saltadas_hoja_larga'] > 0 ? " (saltadas hoja>5: {$v['saltadas_hoja_larga']})" : ''), PHP_EOL;
        }
        if ($fallback) {
            echo "  Modo: FALLBACK (sin .mdb)", PHP_EOL;
        }
    }
}
