<?php

// T-023/T-024 — verificación ejecutable en BD dev
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

// T-023: config + BD resuelven idservicio=23 y el scope lo aplica
$servicio = App\Models\Servicio::find(23);
$scope = App\Models\Carga::servicioActivo()->getQuery()->toSql();
 echo '1) config rem.idservicio: ', Config::get('rem.idservicio'), PHP_EOL;
 echo '2) BD Servicio 23: ', ($servicio ? $servicio->nombre : 'NO EXISTE (FALLA)'), PHP_EOL;
 echo '3) SQL scope servicioActivo: ', $scope, PHP_EOL;

// T-024: login real del admin por consola
$ok = Auth::attempt(['email' => 'admin@redsaludosorno.cl', 'password' => 'cambiar-ya-2026']);
$u = Auth::user();
echo '4) Auth::attempt admin: ', $ok ? 'EXITOSO' : 'FALLÓ', PHP_EOL;
echo '5) rol: ', $u?->rol->value, ' | canAccessPanel(backoffice): ', $u?->canAccessPanel(filament()->getPanel('backoffice')) ? 'true' : 'false', PHP_EOL;
