<?php

// T-025 — muestras colexcep (tipos de valor)
$connection = new COM('ADODB.Connection') or die('COM fail');
$connection->Mode = 1;
$connection->Open('Provider=Microsoft.ACE.OLEDB.12.0;Data Source=C:/xampp/htdocs/www/CargasREM/BD/Global.mdb;Persist Security Info=False;');

$r = $connection->Execute('SELECT TOP 5 * FROM colexcep');
while (! $r->EOF) {
    echo 'codpres=', var_export($r->Fields->Item('codpres')->Value, true),
         ' col=', var_export($r->Fields->Item('col')->Value, true), PHP_EOL;
    $r->MoveNext();
}
// longitud max de codpres
$mx = $connection->Execute('SELECT MAX(LEN(codpres)) AS m, MAX(LEN([col])) AS mc FROM colexcep');
echo 'max codpres=', $mx->Fields->Item('m')->Value, ' max len col=', $mx->Fields->Item('mc')->Value, PHP_EOL;

// col_excep en Excep: muestra valores y max len
$r2 = $connection->Execute('SELECT TOP 5 colexcep FROM Excep');
while (! $r2->EOF) {
    echo 'Excep.colexcep=', var_export($r2->Fields->Item('colexcep')->Value, true), PHP_EOL;
    $r2->MoveNext();
}
$mx2 = $connection->Execute('SELECT MAX(LEN(colexcep)) AS m FROM Excep');
echo 'max len Excep.colexcep=', $mx2->Fields->Item('m')->Value, PHP_EOL;

// Meses sample (para descartar utilidad)
$r3 = $connection->Execute('SELECT TOP 3 * FROM Meses');
while (! $r3->EOF) {
    echo 'Meses nummes=', $r3->Fields->Item('nummes')->Value, ' mes=', var_export($r3->Fields->Item('mes')->Value, true), PHP_EOL;
    $r3->MoveNext();
}
$connection->Close();
