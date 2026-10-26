<?php
/**
 * POC T-008 — GET HTTP + SimpleXML sobre webservicedb.asmx (TEC-08 / RN-029)
 *
 * 1) Guzzle GET sobre los bindings HTTP GET del catálogo: /servicio, /version, /datos
 * 2) SimpleXML parsea el DataSet → array PHP con registros > 0
 * 3) Fixture offline: /servicio y /version completos; /datos guardado solo como muestra
 *    recortada (la lista completa de establecimientos se descarga en la implementación real)
 *
 * Ejecutar: php poc/get_xml_minsal.php
 */

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

$base = 'https://cargasrem.minsal.cl/webservice/webservicedb.asmx/'; // con slash final para que Guzzle apende el op
$dirFixtures = __DIR__ . '/fixtures/minsal';
if (! is_dir($dirFixtures)) {
    mkdir($dirFixtures, 0777, true);
}

$client = new Client([
    'base_uri'        => $base,
    'timeout'         => 30,   // LIM-017 análogo para GET
    'connect_timeout' => 10,
    'headers'         => ['User-Agent' => 'WebCargaREM-POC/1.0'],
]);

/**
 * GET + parseo a array: devuelve [total, muestra, fixtureGuardado]
 * El DataSet ASMX trae el esquema (xs:schema) y las filas en diffgr:diffgram.
 */
function getDataSet(Client $c, string $op, string $fixturePath, int $fixtureLimit = 0): array
{
    $t = microtime(true);
    $resp = $c->get($op); // relativo SIN slash: se agrega al base_uri (.../webservicedb.asmx/servicio)
    $dt = round(microtime(true) - $t, 2);
    $xmlCrudo = (string) $resp->getBody();

    $xml = simplexml_load_string($xmlCrudo);
    if ($xml === false) {
        return [$op, $dt, strlen($xmlCrudo), 0, 'ERROR: XML inválido'];
    }

    // filas: los elementos con msdata:rowOrder dentro del diffgram son los registros reales
    // (el nombre del elemento es el de la TABLA del DataSet: 'idservicio', 'version', 'establecimiento'…"
    $xml->registerXPathNamespace('msdata', 'urn:schemas-microsoft-com:xml-msdata');
    $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');
    $filas = $xml->xpath('//*[@msdata:rowOrder]');
    $registros = [];
    foreach ($filas as $fila) {
        $item = [];
        foreach ($fila->children() as $hijo => $valor) {
            $item[$hijo] = trim((string) $valor);
        }
        $registros[] = $item;
    }

    // fixture: completo o recortado (primeras N filas) para no versionar catálogos enormes
    $paraGuardar = $xmlCrudo;
    if ($fixtureLimit > 0 && count($filas) > $fixtureLimit) {
        $doc = new DOMDocument();
        $doc->loadXML($xmlCrudo);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('msdata', 'urn:schemas-microsoft-com:xml-msdata');
        $todas = $xpath->query('//*[@msdata:rowOrder]');
        for ($i = $todas->length - 1; $i >= $fixtureLimit; $i--) {
            $todas->item($i)->parentNode->removeChild($todas->item($i));
        }
        $paraGuardar = $doc->saveXML();
    }
    file_put_contents($fixturePath, $paraGuardar);

    return [
        'op' => $op,
        'seg' => $dt,
        'bytes' => strlen($xmlCrudo),
        'filas' => count($registros),
        'fixture' => basename($fixturePath) . ($fixtureLimit ? sprintf(' (recorte: %d filas)', $fixtureLimit) : ' (completo)'),
        'muestra' => array_map(
            fn ($r) => array_slice($r, 0, 4, true),
            array_slice($registros, 0, 3)
        ),
    ];
}

$resultados = [];
$registroFall = [];

// 1) /servicio — maestro de servicios (29 esperados)
$getMatches = true;
try {
    $resultados[] = getDataSet($client, 'servicio', "$dirFixtures/servicio_get.xml");
} catch (Throwable $e) {
    $registroFall[] = 'servicio: ' . $e->getMessage();
}

// 2) /Version — versiones de formato por mes/año/serie (¡case-sensitive! en ASMX es 'Version')
try {
    $resultados[] = getDataSet($client, 'Version', "$dirFixtures/version_get.xml");
} catch (Throwable $e) {
    $registroFall[] = 'Version: ' . $e->getMessage();
}

// 3) /datos — establecimientos (catálogo grande: fixture recortado a 20 filas)
try {
    $resultados[] = getDataSet($client, 'datos', "$dirFixtures/datos_get_recorte.xml", 20);
} catch (Throwable $e) {
    $registroFall[] = 'datos: ' . $e->getMessage();
}

foreach ($resultados as $r) {
    echo '[GET] ', $r['op'], ' → ', $r['filas'], " registros en ", $r['seg'], " s (",
         number_format($r['bytes']), " bytes · fixture: ", $r['fixture'], ")\n";
    foreach ($r['muestra'] as $k => $fila) {
        echo '    fila ', $k + 1, ': ', json_encode($fila, JSON_UNESCAPED_UNICODE), "\n";
    }
}
foreach ($registroFall as $f) {
    echo "[ERROR] $f\n";
}

$todosConDatos = count(array_filter($resultados, fn ($r) => $r['filas'] > 0)) === 3;
echo $todosConDatos ? "POC T-008 OK\n" : "POC T-008 con hallazgos\n";
