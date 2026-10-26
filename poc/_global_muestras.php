<?php

// T-025 — muestras de columnas de Thojas / version / Software
$connection = new COM('ADODB.Connection') or die('COM fail');
$connection->Mode = 1;
$connection->Open('Provider=Microsoft.ACE.OLEDB.12.0;Data Source=C:/xampp/htdocs/www/CargasREM/BD/Global.mdb;Persist Security Info=False;');

foreach (['Thojas', 'version', 'Software'] as $t) {
    $r = $connection->Execute('SELECT TOP 3 * FROM [' . $t . ']');
    echo "== $t ==", PHP_EOL;
    for ($i = 0; $i < $r->Fields->Count(); $i++) {
        $f = $r->Fields->Item($i);
        echo '  col: ', $f->Name, ' (tipo ', $f->Type, ')', PHP_EOL;
    }
    while (! $r->EOF) {
        $vals = [];
        for ($i = 0; $i < $r->Fields->Count(); $i++) {
            $vals[] = $r->Fields->Item($i)->Name . '=' . var_export($r->Fields->Item($i)->Value, true);
        }
        echo '  fila: ', implode(' | ', $vals), PHP_EOL;
        $r->MoveNext();
    }
    $r->Close();
}
$connection->Close();
