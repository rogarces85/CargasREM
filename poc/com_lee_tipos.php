<?php
/**
 * POC T-006 — Introspección COM/ACE de BD/DatosSERIEBM2025.mdb (TEC-03 / PD-PLAN-05)
 *
 * 1) Abre la base REAL en modo SOLO LECTURA (adModeRead=1) vía ADODB + ACE
 * 2) Verifica hash SHA-256 antes/después (original intocado)
 * 3) OpenSchema(4) → tabla Datos: nombre/tipo/precisión/escala/largo de col01..col50, Mes y resto
 * 4) Lee la primera fila y muestra valores de muestra (col y Mes) para validar tipos en la práctica
 * 5) Guarda JSON completo en storage/rem/poc/datosseriebm2025_columnas.json (insumo PD-PLAN-05)
 *
 * Ejecutar: php poc/com_lee_tipos.php
 * Nunca escribe sobre BD/ (modo lectura + verificación de hash).
 */

$nombresTipo = [
    2 => 'SmallInt', 3 => 'Integer(Long)', 4 => 'Long', 5 => 'Double', 6 => 'Currency',
    7 => 'adDate', 11 => 'Boolean', 130 => 'VarChar', 131 => 'Numeric', 135 => 'Timestamp',
    202 => 'VarWChar', 203 => 'LongVarWChar',
];

$origen = __DIR__ . '/../BD/DatosSERIEBM2025.mdb';
if (! is_file($origen)) {
    fwrite(STDERR, "FATAL: no existe BD/DatosSERIEBM2025.mdb\n");
    exit(1);
}

$hashPre = hash_file('sha256', $origen);
echo '[1] base real: ', realpath($origen), ' · ', number_format(filesize($origen)), " bytes\n";
echo '    HASH pre:  ', $hashPre, "\n";

// ── 1. Conexión SOLO LECTURA ──────────────────────────────────
$cn = null;
$providerUsado = null;
foreach (['Microsoft.ACE.OLEDB.12.0', 'Microsoft.ACE.OLEDB.16.0'] as $prov) {
    try {
        $cn = new COM('ADODB.Connection');
        $cn->ConnectionString = "Provider=$prov;Data Source=" . realpath($origen) . ';Persist Security Info=False;';
        $cn->Mode = 1; // adModeRead
        $cn->Open();
        $providerUsado = $prov;
        break;
    } catch (Throwable $e) {
        echo "    [aviso] $prov falló: ", $e->getMessage(), "\n";
        $cn = null;
    }
}
if ($cn === null) {
    fwrite(STDERR, "FATAL: ningún proveedor ACE disponible\n");
    exit(1);
}
echo "[2] conexión SOLO LECTURA OK vía $providerUsado\n";

// columnas de Datos
$rs = $cn->OpenSchema(4, [null, null, 'Datos']);
$cols = [];
while (! $rs->EOF) {
    $cols[] = [
        'name' => (string) $rs->Fields->Item('COLUMN_NAME')->Value,
        'type' => (int) $rs->Fields->Item('DATA_TYPE')->Value,
        'prec' => $rs->Fields->Item('NUMERIC_PRECISION')->Value,
        'scale' => $rs->Fields->Item('NUMERIC_SCALE')->Value,
        'len' => $rs->Fields->Item('CHARACTER_MAXIMUM_LENGTH')->Value,
        'null' => (int) $rs->Fields->Item('IS_NULLABLE')->Value,
    ];
    $rs->MoveNext();
}
$rs->Close();
echo '[3] tabla Datos: ', count($cols), " columnas\n";

// tabla resumida de tipos (foco PD-PLAN-05: col01..col50 + Mes)
$deInteres = fn ($n) => preg_match('/^col\d+$/i', $n) || str_starts_with(strtolower($n), 'col01') || strtolower($n) === 'mes';
foreach ($cols as $c) {
    $nom = $c['name'];
    if (preg_match('/^col/i', $nom) || strtolower($nom) === 'mes') {
        echo sprintf("    %-10s %-14s prec=%-4s scale=%-4s len=%-4s null=%s\n",
            $nom, $nombresTipo[$c['type']] ?? 'tipo' . $c['type'],
            $c['prec'] ?? '-', $c['scale'] ?? '-', $c['len'] ?? '-', $c['null']);
    }
}
// resto de columnas (no col/mes)
echo '    resto: ';
$resto = array_filter($cols, fn ($c) => ! preg_match('/^col/i', $c['name']) && strtolower($c['name']) !== 'mes');
echo implode(' | ', array_map(fn ($c) => $c['name'] . ':' . ($nombresTipo[$c['type']] ?? $c['type']), $resto)), "\n";

// ── 4. Primera fila: valores de muestra ───────────────────────
$rs2 = $cn->Execute('SELECT TOP 1 * FROM [Datos] ORDER BY [Ano], [Mes]');
if ($rs2->EOF) {
    echo "[4] la tabla Datos está vacía\n";
    $muestra = null;
} else {
    $muestra = [];
    foreach ($cols as $c) {
        $f = $rs2->Fields->Item($c['name']);
        $muestra[$c['name']] = ['valor' => $f->Value, 'tipo_php' => gettype($f->Value)];
    }
    echo "[4] muestra primera fila (valores/tipos PHP de llegada):\n";
    foreach ($muestra as $nom => $m) {
        if (preg_match('/^col/i', $nom) || strtolower($nom) === 'mes') {
            // columnas de interés: mostrar valor recortado
            $v = $m['valor'];
            $vs = is_string($v) ? $v : json_encode($v);
            if (strlen($vs) > 20) $vs = substr($vs, 0, 20) . '…';
            echo sprintf("    %-10s %-18s %s\n", $nom, $m['tipo_php'], $vs);
        }
    }
}
$rs2->Close();
$cn->Close();

// verificación de integridad
$hashPost = hash_file('sha256', $origen);
echo '    HASH post: ', $hashPost, ' ', ($hashPost === $hashPre ? '(intacto ✓)' : '(¡CAMBIÓ! ✗)'), "\n";

// ── 5. JSON para PD-PLAN-05 ───────────────────────────────────
$dir = __DIR__ . '/../storage/rem/poc';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}
file_put_contents("$dir/datosseriebm2025_columnas.json", json_encode([
    'fuente' => realpath($origen),
    'hash_sha256' => $hashPre,
    'modo' => 'solo lectura (adModeRead)',
    'columnas_datos' => $cols,
    'muestra_fila_1' => $muestra,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "[5] JSON de tipos guardado en storage/rem/poc/datosseriebm2025_columnas.json\n";
echo 'POC T-006 OK', "\n";
