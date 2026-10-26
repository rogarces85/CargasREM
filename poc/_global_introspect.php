<?php

// T-025 — introspección de BD/Global.mdb (solo lectura): tablas + conteos
$connection = new COM('ADODB.Connection') or die('COM fail');
$connection->Mode = 1; // adModeRead
$connection->Open('Provider=Microsoft.ACE.OLEDB.12.0;Data Source=C:/xampp/htdocs/www/CargasREM/BD/Global.mdb;Persist Security Info=False;');

$rst = $connection->OpenSchema(20); // adSchemaTables
while (! $rst->EOF) {
    $name = (string) $rst->Fields->Item(2)->Value;  // TABLE_NAME
    $type = (string) $rst->Fields->Item(3)->Value;  // TABLE_TYPE

    if (in_array($type, ['TABLE', 'ACCESS TABLE'], true) && ! str_starts_with($name, 'MSys')) {
        $c = $connection->Execute('SELECT COUNT(*) AS c FROM [' . $name . ']');
        printf('%s = %d%s', $name, (int) $c->Fields->Item('c')->Value, PHP_EOL);
        $c->Close();
    }
    $rst->MoveNext();
}
$connection->Close();
