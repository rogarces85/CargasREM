<?php

// WebCargaREM — mensajes del dominio REM (T-010)
// Catálogo LIM completo desde specs/SPECS.md (LIM-001..LIM-024).
// Referencia de mensajes en el código: __('rem.lim.LIM-005') / config('rem.excel.mensajes').

return [
    'lim' => [
        'LIM-001' => 'Abra y guarde el archivo en Excel para calcular fórmulas. No es posible leer los valores del archivo tal como fue entregado.',
        'LIM-002' => 'La extensión del archivo no corresponde al año indicado.',
        'LIM-003' => 'Establecimiento no pertenece al servicio activo.',
        'LIM-004' => 'Mes en texto (B6) no coincide con mes numérico (C6+D6).',
        'LIM-005' => 'Versión del formato no corresponde al mes/año/serie.',
        'LIM-006' => 'Hoja :hoja no configurada para esta serie/año.',
        'LIM-007' => 'Prestación no está definida en el diccionario para esa hoja/serie/año (se ignora).',
        'LIM-008' => 'Valor con más de un decimal. La fila completa se descarta.',
        'LIM-009' => 'Valor con un decimal en hoja no exceptuada.',
        'LIM-010' => 'Valor negativo o no numérico.',
        'LIM-011' => 'Fila vacía (todo en cero o nulo): no se registra.',
        'LIM-012' => 'Ya existe una carga para este establecimiento/mes/año/serie. ¿Desea reemplazarla?',
        'LIM-013' => 'La recepción está fuera de la ventana de carga. Se registra con estado fuera de plazo.',
        'LIM-014' => 'Ya utilizó su 2da subida permitida fuera de plazo.',
        'LIM-015' => 'Intento de subir una serie no asignada al establecimiento.',
        'LIM-016' => 'Ventana cerrada durante la subida: se procesa igualmente (en_plazo calculado al momento de recepción).',
        'LIM-017' => 'El servicio del Minsal no responde. Intente nuevamente; si persiste se puede importar desde la base local (Global.mdb).',
        'LIM-018' => 'No existen datos para este año y serie.',
        'LIM-019' => 'El motor COM/ACE (Microsoft Access Database Engine) no está disponible en el servidor. Bloquea la generación del archivo Minsal.',
        'LIM-020' => 'El archivo excede el tamaño máximo permitido (:max Mb).',
        'LIM-021' => 'Su cuenta está inactiva. Contacte al administrador.',
        'LIM-022' => 'MFA no disponible. El administrador debe reestablecer su segundo factor.',
        'LIM-023' => 'El backup automático falló. Se alerta en el panel del administrador y se reintenta en el próximo ciclo.',
        'LIM-024' => 'La migración quedó parcial: bases faltantes :faltantes. Se continuó con las disponibles.',
    ],
];
