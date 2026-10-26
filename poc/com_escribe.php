<?php
/**
 * POC T-005 — Escritura COM/ACE sobre copia de MASTER.mdb (TEC-03 / RF-014)
 * 
 * 1) Copia BD/MASTER.mdb (intocado) a storage/rem/poc/MASTER_poc.mdb
 * 2) Conexión ADODB (Provider=Microsoft.ACE.OLEDB.12.0, fallback 16.0)
 * 3) OpenSchema: lista tablas + columnas/tipos de Datos y Registros
 * 4) INSERT parametrizado de 2 filas en Datos (con decimales y texto acentuado)
 * 5) SELECT y comparación valor a valor (incl. normalización coma/punto RN-037)
 * 6) DELETE de las filas de prueba, cierre y verificación de conteo
 * 
 * Ejecutar: php poc/com_escribe.php
 * Nunca modifica BD/ — todo ocurre sobre la copia en storage/rem/poc/.
 */

const MARCA = 'POC-T-005';

// ── 1. Copia de la plantilla ─────────────────────────────────────
$origen = __DIR__ . '/../BD/MASTER.mdb';
$destino = __DIR__ . '/../storage/rem/poc/MASTER_poc.mdb';
if (! is_file($origen)) {
    fwrite(STDERR, "FATAL: no existe BD/MASTER.mdb\n");
    exit(1);
}
if (! is_dir(dirname($destino))) {
    mkdir(dirname($destino), 0777, true);
}
if (is_file($destino)) {
    unlink($destino);
}
copy($origen, $destino) || exit(fwrite(STDERR, "FATAL: falló la copia de la plantilla\n") ?: 1);
$hashPre = hash_file('sha256', $origen);
echo '[1] plantilla copiada: ', basename($origen), ' → ', $destino, "\n";
echo '    original BD/MASTER.mdb: ', number_format(filesize($origen)), ' bytes (HASH pre: ', $hashPre, ")\n";

// ── 2. Conexión ADODB con fallback ──────────────────────────────
$cn = null;
$providerUsado = null;
foreach (['Microsoft.ACE.OLEDB.12.0', 'Microsoft.ACE.OLEDB.16.0'] as $prov) {
    try {
        $cn = new COM('ADODB.Connection');
        $cn->ConnectionString = "Provider=$prov;Data Source=" . realpath($destino) . ';Persist Security Info=False;';
        $cn->Open();
        $providerUsado = $prov;
        break;
    } catch (Throwable $e) {
        echo "    [aviso] $prov falló: ", $e->getMessage(), "\n";
        $cn = null;
    }
}
if ($cn === null) {
    fwrite(STDERR, "FATAL: ningún proveedor ACE disponible (12.0 ni 16.0)\n");
    exit(1);
}
echo "[2] conexión OK vía $providerUsado\n";

/**
 * Lista de columnas de una tabla desde OpenSchema (adSchemaColumns=4)
 */
function columnas(COM $cn, string $tabla): array
{
    $rs = $cn->OpenSchema(4, [null, null, $tabla]);
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

    return $cols;
}

function nombreTipo(int $t): string
{
    return [
        2 => 'SmallInt', 3 => 'Integer', 4 => 'Long', 5 => 'Double', 6 => 'Currency',
        7 => 'Date', 11 => 'Boolean', 131 => 'Numeric', 135 => 'Timestamp',
        202 => 'VarWChar', 203 => 'LongVarWChar',
    ][$t] ?? "tipo$t";
}

// ── 3. Estructura: tablas + columnas de Datos/Registros ─────────
$rs = $cn->OpenSchema(20); // adSchemaTables, sin filtro (el filtrado en ADO falló sobre Access)
$tablas = [];
while (! $rs->EOF) {
    if ((string) $rs->Fields->Item('TABLE_TYPE')->Value === 'TABLE'
        && ! str_starts_with((string) $rs->Fields->Item('TABLE_NAME')->Value, 'MSys')) {
        $tablas[] = (string) $rs->Fields->Item('TABLE_NAME')->Value;
    }
    $rs->MoveNext();
}
$rs->Close();
echo '[3] tablas en la plantilla: ', implode(', ', $tablas), "\n";

$colsDatos = columnas($cn, 'Datos');
$colsReg = columnas($cn, 'Registros');
echo '    Datos: ', count($colsDatos), " columnas\n";
foreach ($colsDatos as $i => $c) { // solo primeras 8 para no inundar
    if ($i >= 8) { echo "    ... (", count($colsDatos) - $i, ' más)'; break; }
    echo "      {$c['name']} :: ", nombreTipo($c['type']),
         $c['prec'] ? " (prec={$c['prec']} scale={$c['scale']})" : '',
         $c['len'] ? " (len={$c['len']})" : '', "\n";
}
echo '    Registros: ', implode(' | ', array_column($colsReg, 'name')), "\n";

// documento completo de tipos para H0
file_put_contents(__DIR__ . '/../storage/rem/poc/master_datos_columnas.json', json_encode($colsDatos, JSON_PRETTY_PRINT));

// ── 4. INSERT parametrizado de 2 filas en Datos ─────────────────
// Esquema real de la plantilla: Ano/Mes Long · CodigoPrestacion varchar(50) ·
// IdEstablecimiento varchar(7) · IdServicio varchar(2) · fechaIngreso adDate · Col01..50 Double
$campos = array_column($colsDatos, 'name');
$nombres = '[' . implode('],[', $campos) . ']';
$n = count($campos);
$placeholders = implode(',', array_fill(0, $n, '?'));

try {
    $cmd = new COM('ADODB.Command');
    $cmd->ActiveConnection = $cn;
    $cmd->CommandText = "INSERT INTO [Datos] ($nombres) VALUES ($placeholders)";
    $cmd->Prepared = false;

    // constantes ADO: tipo columna (DATA_TYPE) → tipo parámetro
    $tPara = [2 => 16, 3 => 3, 4 => 3, 5 => 5, 6 => 6, 7 => 7, 11 => 11, 130 => 200, 131 => 14, 135 => 135,
              202 => 202, 203 => 203]; // 130=VarChar→200(adVarChar)
    if (! defined('VT_DATE')) define('VT_DATE', 7);

    for ($f = 0; $f < 2; $f++) {
        while ($cmd->Parameters->Count > 0) {
            $cmd->Parameters->Delete(0);
        }
        $valores = [];
        foreach (array_map(fn ($c) => (object) $c, $colsDatos) as $c) {
            $name = $c->name;
            switch ($name) {
                case 'Ano':                 $valores[] = 2026; break;
                case 'Mes':                 $valores[] = 10;   break;
                case 'IdEstablecimiento':    $valores[] = 'T005-F' . ($f + 1); break; // exacto 7 chars
                case 'IdServicio':           $valores[] = '01'; break;               // exacto 2 chars
                case 'CodigoPrestacion':     $valores[] = 'POC-T-005-F' . ($f + 1) . '-ÁÉÍÓÚÑ'; break; // ≤50, con acentos/ñ
                case 'fechaIngreso':         $valores[] = new VARIANT(date('Y-m-d H:i:s'), VT_DATE); break; // adDate exige variante fecha
                default:
                    if (preg_match('/^col/i', $name)) {
                        // decimales: siempre float nativo (los strings solo sirven para campos VarChar)
                        $valores[] = ($f + 1) * 1000 + (int) preg_replace('/\D/', '', $name) * 0.125;
                    } elseif (in_array($c->type, [202, 203, 130], true)) {
                        $valores[] = substr(MARCA, 0, max(1, (int) min(strlen(MARCA), (int) ($c->len ?? 9))));
                    } elseif (in_array($c->type, [2, 3, 4, 5, 6, 131], true)) {
                        $valores[] = $f + 1;
                    } elseif ($c->type === 11) {
                        $valores[] = false;
                    } elseif (in_array($c->type, [7, 135], true)) {
                        $valores[] = new VARIANT(date('Y-m-d H:i:s'), VT_DATE);
                    } else {
                        $valores[] = substr(MARCA, 0, max(1, (int) min(strlen(MARCA), (int) ($c->len ?? 9))));
                    }
            }
        }
        foreach ($valores as $i => $v) {
            $c = (object) $colsDatos[$i];
            $p = $cmd->CreateParameter('p' . $i, $tPara[$c->type] ?? 200, 1 /*adParamInput*/, 0);
            $p->Value = $v;
            if (is_string($v)) {
                // tamaño del parámetro nunca mayor al campo (evita "campo demasiado pequeño")
                $p->Size = $c->len !== null && $c->len > 0 ? min(strlen($v), (int) $c->len) : max(1, strlen($v));
            } else {
                $p->Size = 0;
            }
            $cmd->Parameters->Append($p);
        }
        $cmd->Execute();
        echo '[4] fila ', $f + 1, " insertada en Datos\n";
    }

    // ── 5. Lectura de vuelta y comparación exacta ─────────────────
$marcaCampo = 'IdEstablecimiento';
$rs2 = $cn->Execute("SELECT * FROM [Datos] WHERE [$marcaCampo] LIKE 'T005-F%' ORDER BY [$marcaCampo]");
$leidas = [];
while (! $rs2->EOF) {
    $fila = [];
    foreach ($campos as $nombre) {
        $fila[$nombre] = $rs2->Fields->Item($nombre)->Value;
    }
    $leidas[] = $fila;
    $rs2->MoveNext();
}
$rs2->Close();
echo '[5] filas leídas T005-F%: ', count($leidas), " (esperado 2)\n";
$ok = count($leidas) === 2;
foreach ($leidas as $k => $fila) {
    $f = $k + 1;
    $esperados = [
        'IdEstablecimiento' => 'T005-F' . $f,
        'IdServicio'        => '01',
        'CodigoPrestacion'  => 'POC-T-005-F' . $f . '-ÁÉÍÓÚÑ',
        'Col01'             => $f * 1000 + 0.125,
        'Ano'               => 2026,
        'Mes'               => 10,
    ];
    $muestra = [];
    foreach ($esperados as $campo => $esperado) {
        $muestra[$campo] = ['leído' => $fila[$campo], 'esperado' => $esperado, 'tipo_pdf' => gettype($fila[$campo]), 'match' => (is_numeric($esperado) ? abs((float) $fila[$campo] - $esperado) < 1e-9 : $fila[$campo] === $esperado)];
    }
    $todoOk = ! in_array(false, array_column($muestra, 'match'), true);
    $ok = $ok && $todoOk;
    echo '    fila ', $k + 1, ': ', json_encode($muestra, JSON_UNESCAPED_UNICODE), ($todoOk ? ' ✓' : ' ✗'), "\n";
}

// ── 6. DELETE de filas de prueba + verificación conteo 0 ─────
$cn->Execute("DELETE FROM [Datos] WHERE [$marcaCampo] LIKE 'T005-F%'");
$rs3 = $cn->Execute("SELECT COUNT(*) AS total FROM [Datos] WHERE [$marcaCampo] LIKE 'T005-F%'");
$restan = (int) $rs3->Fields->Item('total')->Value;
$rs3->Close();
echo "[6] DELETE ejecutado — filas restantes con marca: $restan (esperado 0)\n";
$ok = $ok && $restan === 0;

$cn->Close();
$hashPost = hash_file('sha256', $origen);
echo "    BD/MASTER.mdb HASH post: $hashPost ", ($hashPost === $GLOBALS['hashPre'] ? '(intacto ✓)' : '(¡CAMBIÓ! ✗)'), "\n";
echo $ok ? "POC T-005 OK\n" : "POC T-005 con hallazgos (ver mensajes arriba)\n";
exit($ok ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . "\n");
    if (isset($cn) && $cn->State !== 0) {
        $cn->Close();
    }
    exit(1);
}

