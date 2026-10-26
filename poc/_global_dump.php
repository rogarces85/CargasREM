<?php

// T-025 — introspección de BD/Global.mdb: dump crudo OpenSchema(4)
$connection = new COM('ADODB.Connection') or die('COM fail');
$connection->Mode = 1; // adModeRead
$connection->Open('Provider=Microsoft.ACE.OLEDB.12.0;Data Source=C:/xampp/htdocs/www/CargasREM/BD/Global.mdb;Persist Security Info=False;');

$rst = $connection->OpenSchema(4);
$ncols = $rst->Fields->Count();
echo 'campos: ', $ncols, PHP_EOL;
$filas = 0;
while (! $rst->EOF) {
    $vals = [];
    for ($i = 0; $i < $ncols; $i++) {
        $f = $rst->Fields->Item($i);
        $vals[] = $f->Name . '=' . var_export($f->Value, true);
    }
    echo implode(' | ', $vals), PHP_EOL;
    $filas++;
    if ($filas >= 60) {
        break;
    }
    $rst->MoveNext();
}
echo 'filas mostradas: ', $filas, PHP_EOL;
$rst->Close();
$connection->Close();
