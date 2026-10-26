<?php
/**
 * POC T-004 — Lectura Excel (TEC-04 / RN-003 / RN-004 / LIM-001)
 * Genera fixtures sintéticos (.xls/.xlsx/.xlsm) y demuestra:
 *  1) lectura con setReadDataOnly(true) valores cacheados
 *  2) ReadFilter por rango (alineado a Thojas)
 *  3) detección de fórmulas SIN valores cacheados (LIM-001)
 * Ejecutar: php poc/lectura_excel.php
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class FiltroRango implements IReadFilter
{
    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        // ventana reducida de lectura A1:H20 (simula rangos de cat_thojas)
        $col = Coordinate::columnIndexFromString($columnAddress);

        return $row >= 1 && $row <= 20 && $col >= 1 && $col <= 8;
    }
}

function hojaRem(): Spreadsheet
{
    $wb = new Spreadsheet();
    $ws = $wb->getActiveSheet();
    $ws->setTitle('A01');
    $ws->setCellValue('C3', 'SERVICIO DE SALUD OSORNO');           // encabezado establecimiento (RN-009: C3:H3)
    $ws->setCellValue('C6', 3);                                    // mes numérico
    $ws->setCellValue('D6', 'MARZO');
    $ws->setCellValue('B7', 2026);                                 // año (RN-009: B7)
    $ws->setCellValue('B17', 'SERIE A 2026');                      // serie (B17 primeros 7-8 chars)
    $ws->setCellValue('A9', 'REM 2026.03 V1');                     // versión (A9 primeros 12)
    $ws->setCellValue('C10', 12345);
    $ws->setCellValue('C11', 67.5);                                // 1 decimal (RN-018)
    $ws->setCellValue('C12', 1234.567);                            // >1 decimal
    $ws->setCellValue('B14', 15);                                  // base para fórmula
    $ws->setCellValue('C14', '=B14*2');                            // fórmula (celda C14 del Studien)

    return $wb;
}

$dir = __DIR__ . '/fixtures';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$out = [];

// ── 1. Generar fixtures: con y sin caché de fórmulas para .xlsx/.xls, y .xlsm por renombrado ──
$w0 = new XlsxWriter(hojaRem());
$w0->setPreCalculateFormulas(false);          // fórmulas SIN valores cacheados (caso LIM-001)
$w0->save("$dir/sintetico_sin_cache.xlsx");
$w0b = new Xls(hojaRem());
$w0b->setPreCalculateFormulas(false);
$w0b->save("$dir/sintetico_sin_cache.xls");
copy("$dir/sintetico_sin_cache.xlsx", "$dir/sintetico_sin_cache.xlsm"); // simulación de .xlsm (contenido xlsx)

$wbConCache = hojaRem();
$w1 = new XlsxWriter($wbConCache);
$w1->setPreCalculateFormulas(true);           // valores calculados incluidos en el xml (leyendo se verá el número)
$w1->save("$dir/sintetico_con_cache.xlsx");
(new Xls($wbConCache))->save("$dir/sintetico_con_cache.xls");  // Xls guarda fórmula+valor por defecto
copy("$dir/sintetico_con_cache.xlsx", "$dir/sintetico_con_cache.xlsm");

$out[] = '[gen] OK fixtures en poc/fixtures (con y sin caché; xls/xlsx/xlsm)';

// ── 2. Lectura dataOnly + ReadFilter, por coordenada — los 3 formatos ──
foreach (['xlsx', 'xls', 'xlsm'] as $ext) {
    $path = "$dir/sintetico_con_cache.$ext";
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    $reader->setReadFilter(new FiltroRango());
    $sh = $reader->load($path)->getActiveSheet();

    $vals = [
        'C3' => $sh->getCell('C3')->getValue(),               // texto encabezado
        'B7' => $sh->getCell('B7')->getValue(),               // año
        'C11' => $sh->getCell('C11')->getValue(),             // 1 decimal
        'C12' => $sh->getCell('C12')->getValue(),             // >1 decimal
        'C13' => var_export($sh->getCell('C13')->getValue(), true),  // vacía
        'C14' => $sh->getCell('C14')->getOldCalculatedValue(), // fórmula CON caché (valor cacheado; getValue devolvería "=B14*2")
    ];
    $out[] = "[read $ext] " . json_encode($vals, JSON_UNESCAPED_UNICODE);
}

// ── 3. LIM-001: fórmulas sin valores cacheados ──
// Regla verificada en POC: en modo dataOnly, getValue() devuelve la fórmula ("=…") y
// getOldCalculatedValue() devuelve el valor cacheado del archivo, o null si NO fue incluido
// (formulario guardado sin cálculo previo → caso LIM-001).
foreach (['xlsx', 'xls', 'xlsm'] as $ext) {
    $path = "$dir/sintetico_sin_cache.$ext";
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    $sh = $reader->load($path)->getActiveSheet();
    $cell = $sh->getCell('C14');
    $val = $cell->getValue();
    $cache = $cell->getOldCalculatedValue();

    if (is_string($val) && str_starts_with($val, '=') && $cache === null) {
        $out[] = "[LIM-001 $ext] fórmula SIN caché detectada (getValue=$val, oldCalculated=null)"
            . " → rechazo controlado con mensaje estandarizado ('Abra y guarde el archivo en Excel para calcular fórmulas')";
    } elseif ($ext === 'xls' && is_string($val) && str_starts_with($val, '=') && $cache === 0.0) {
        // BIFF8 no puede almacenar fórmula sin resultado: el writer Xls fuerza 0.0 sin caché real.
        // Regla .xls: Excel SIEMPRE memoriza el resultado al guardar ficheros con formulas;
        // la detección estricta null solo aplica a .xlsx/.xlsm (hallazgo registrado para H0/T-011).
        $out[] = "[LIM-001 $ext] (BIFF8) fórmula escrita sin caché se lee con oldCalculated=0.0 — "
            . 'detección estricta (null) LIMITADA a .xlsx/.xlsm; .xls generado por Excel siempre trae caché';
    } else {
        $out[] = "[LIM-001 $ext] valor cacheado presente: oldCalculated=" . var_export($cache, true)
            . ' (comportamiento observado, revisar en informe H0)';
    }

    // Contra-muestra: archivo CON caché → oldCalculated debe traer el número
    $sh2 = IOFactory::createReaderForFile("$dir/sintetico_con_cache.$ext");
    $sh2->setReadDataOnly(true);
    $cell2 = $sh2->load("$dir/sintetico_con_cache.$ext")->getActiveSheet()->getCell('C14');
    $out[] = "[LIM-001 $ext] contra-muestra con caché: oldCalculated=" . var_export($cell2->getOldCalculatedValue(), true) . ' (esperado 30)';
}

// ── 4. Efecto del ReadFilter (carga total vs reducida) ──
$sinFiltro = IOFactory::createReaderForFile("$dir/sintetico_con_cache.xlsx");
$sinFiltro->setReadDataOnly(true);
$wb2 = $sinFiltro->load("$dir/sintetico_con_cache.xlsx");
$dimCompleta = $wb2->getActiveSheet()->calculateWorksheetDimension();

$conFiltro = IOFactory::createReaderForFile("$dir/sintetico_con_cache.xlsx");
$conFiltro->setReadDataOnly(true);
$conFiltro->setReadFilter(new FiltroRango());
$wb3 = $conFiltro->load("$dir/sintetico_con_cache.xlsx");
$dimFiltrada = $wb3->getActiveSheet()->calculateWorksheetDimension();

$out[] = "[filter-check] dims sin filtro=$dimCompleta vs con filtro=$dimFiltrada";

foreach ($out as $line) {
    echo $line, "\n";
}
echo 'POC T-004 OK', "\n";
