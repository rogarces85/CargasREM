<?php
/**
 * POC T-007 — SoapClient contra el WSDL Minsal (TEC-08 / LIM-017)
 *
 * 1) SoapClient con WSDL en vivo, exceptions=1, connection_timeout y timeout (LIM-017)
 * 2) Llama a la operación SOAP Version (catálogo más barato) y a servicio (29 filas)
 * 3) Registra el XML crudo de request/response (trace=1) como evidencia
 * 4) Archiva muestra en poc/fixtures/minsal/ para las pruebas offline
 *
 * Ejecutar: php poc/soap_minsal.php
 */

ini_set('user_agent', 'WebCargaREM-POC/1.0');

$base = 'https://cargasrem.minsal.cl/webservice';
$wsdlUrl = "$base/webservicedb.asmx?WSDL";
$dirFixtures = __DIR__ . '/fixtures/minsal';
if (! is_dir($dirFixtures)) {
    mkdir($dirFixtures, 0777, true);
}

echo "[1] WSDL: $wsdlUrl\n";

$start = microtime(true);
try {
    $client = new SoapClient($wsdlUrl, [
        'trace'              => 1,
        'exceptions'         => 1,        // LIM-017: excepciones SOAP explícitas
        'connection_timeout' => 10,
        'timeout'            => 30,
        'cache_wsdl'         => WSDL_CACHE_NONE, // POC: siempre traer el WSDL real
        'compression'        => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
        'features'           => SOAP_SINGLE_ELEMENT_ARRAYS,
    ]);
    echo '[1] SoapClient inicializado en ', round(microtime(true) - $start, 2), " s\n";
    echo '    funciones expuestas: ', implode(', ', array_slice($client->__getFunctions(), 0, 10)), "\n";
    file_put_contents("$dirFixtures/webservicedb.wsdl.xml", file_get_contents($wsdlUrl) ?: '');
    echo "    WSDL archivado en fixtures/minsal/webservicedb.wsdl.xml\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL SoapClient: ' . $e->getMessage() . "\n");
    exit(1);
}

// ── 2. Operación Version (la más liviana) ──────────────────────
$funciones = $client->__getFunctions();
$formas = fn (string $op): ?string => array_values(array_filter(
    $funciones, fn ($f) => stripos($f, " $op(") !== false
))[0] ?? null;

foreach (['Version' => 'version.xml', 'servicio' => 'servicio.xml'] as $op => $fixture) {
    if ($formas($op) === null) {
        echo "    [aviso] operación $op no consta en el WSDL; se omite\n";
        continue;
    }
    $t = microtime(true);
    try {
        $resp = call_user_func([$client, $op]);
        $dt = round(microtime(true) - $t, 2);

        // respuesta cruda (trace) y también la estructurada
        $xmlResp = $client->__getLastResponse();
        $xmlReq  = $client->__getLastRequest();
        file_put_contents("$dirFixtures/$fixture", $xmlResp ?: '');
        file_put_contents("$dirFixtures/" . str_replace('.xml', '.request.xml', $fixture), $xmlReq ?: '');

        echo "[2] $op OK en $dt s\n";
        // extraer conteo de filas del DataSet de la respuesta SOAP
        $xml = simplexml_load_string($xmlResp);
        $filas = ($xml !== false) ? $xml->xpath('//*[local-name()="servicio"]') : false;
        echo '    respuesta cruda: ', number_format(strlen($xmlResp)), ' bytes → ', count($filas ?: []), " nodos servicio\n";
        echo '    respuesta estructurada (var_dump de tipo): ', get_class($resp) === 'stdClass' ? 'stdClass' : gettype($resp), "\n";
    } catch (SoapFault $e) {
        echo "    [LIM-017] SoapFault en $op: ", $e->getMessage(), "\n";
    } catch (Throwable $e) {
        echo '    [timeout/error] en ' . $op . ': ', $e->getMessage(), "\n";
    }
}

echo 'POC T-007 OK (evidencia + fixtures en poc/fixtures/minsal/)', "\n";
