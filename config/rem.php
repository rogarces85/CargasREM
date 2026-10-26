<?php

// WebCargaREM — configuración del dominio REM (Servicio de Salud Osorno)
// T-010 · Refs: TEC-01, RNF-016, §11 PLAN
// Los secretos NO viven aquí: se enumeran en .env.example y se resuelven con env().

return [

    // Servicio de salud activo para todas las cargas (RF-021).
    // El sistema anterior lo tomaba de Trans.mdb → SetServicio (irreversible);
    // aquí queda configurable y auditable.
    'idservicio' => env('REM_IDSERVICIO', '23'),
    'servicio_nombre' => env('REM_SERVICIO_NOMBRE', 'Servicio de Salud Osorno'),

    // ── Integración Minsal (TEC-08 / T-007 / T-008, LIM-017) ─────────
    'minsal' => [
        'base_uri' => env('REM_MINSAL_BASE_URI', 'https://cargasrem.minsal.cl/webservice/'),
        'webservicedb' => env('REM_MINSAL_WEBDB', 'webservicedb.asmx'),

        // vía primaria HTTP GET + SimpleXML (decisión PO T-008)
        'timeout' => (int) env('REM_MINSAL_TIMEOUT', 30),
        'connect_timeout' => (int) env('REM_MINSAL_CONNECT_TIMEOUT', 10),

        // respaldo SOAP (decisión PO T-007)
        'soap' => [
            'exceptions' => true,
            'connection_timeout' => (int) env('REM_SOAP_CONNECT_TIMEOUT', 10),
            'timeout' => (int) env('REM_MINSAL_TIMEOUT', 30),
            'cache_wsdl' => WSDL_CACHE_DISK,
        ],

        // nombres de métodos ASMX — **case-sensitive** (hallazgo PO T-008)
        'operaciones_catalogo' => ['datos', 'servicio', 'diccionario', 'Thoja', 'Version', 'Excep', 'ColExcep'],

        // Descarga de histórico (CargaSerieServ / CargaControlServ, RF-020)
        'descarga_historico' => [
            'datos_serie' => 'CargaSerieServ.asmx',
            'datos_control' => 'CargaControlServ.asmx',
        ],
    ],

    // ── Colas y worker Windows (TEC-05 / T-009, MOD-05) ──────────────
    'colas' => [
        'driver' => env('QUEUE_CONNECTION', 'database'),
        'sleep' => 1,
        'tries' => 3,
        'max_time' => 3600, // reinicio diario del worker vía Task Scheduler
        // nombre del script BAT que el Task Scheduler ejecuta cada minuto (SkipIfRunning)
        'worker_script' => 'scripts/rem_worker_colas.bat',
        // nombre de la tarea programada instancia dev (se crea en provisionamiento del servidor)
        'tarea_programada' => env('REM_WORKER_TASK_NAME', 'WebCargaREM_worker'),
    ],

    // ── Carga de archivos Excel (TEC-04 / RN-003/RN-004 / LIM-020) ───
    'excel' => [
        'max_upload_mb' => (int) env('REM_EXCEL_MAX_MB', 20),
        'formatos' => ['xls', 'xlsx', 'xlsm'],
        'rango_lectura_hojas' => ['max_filas' => 200, 'max_col' => 'AZ'], // ventana IReadFilter
        'mensajes' => 'rem.lim', // catálogo lang/es/rem.php para los LIM
    ],

    // ── Generador del archivo Minsal (RF-014, LIM-019, RIES-01) ──────
    'generador' => [
        'plantilla_minsal' => 'MASTER.mdb',            // de docs/BD-LOCAL.md
        'gzip_nivel' => 9,
        'borrar_mdb_intermedio' => true,
        'prefijo_archivo' => 'CargaRem',
    ],

    // ── Canales de despacho (RNF-016 / PEND-007) ─────────────────────
    'smtp' => [
        // Laravel usa config/mail.php; aquí van parámetros propios de rem (asunto/destinos)
        'destino_accidentes' => env('REM_SMTP_DESTINO_ACCIDENTES', 'rem@ssosorno.cl'),
        'asunto_carga' => env('REM_SMTP_ASUNTO', '[WebCargaREM] soporte de carga'),
    ],
    'ftp' => [
        // PEND-007: credenciales aún no entregadas — placeholder, se resuelven en .env del servidor
        'host' => env('REM_FTP_HOST', ''),
        'puerto' => (int) env('REM_FTP_PORT', 21),
        'usuario' => env('REM_FTP_USER', ''),
        'detalle' => env('REM_FTP_DETALLE', ''),
    ],

    // ── Ventanas de carga (RN-038..RN-042 / RN-031/RN-032) ───────────
    'ventanas' => [
        // PEND-009: mes límite sin resolución definitiva; provisionable env
        'mes_limite' => env('REM_MES_LIMITE', ''),
        'subidas_fuera_plazo_maximo' => 2, // LIM-014: 2da subida permitida → 3a bloqueada
    ],

    // ── Backups (RNF-010/011, LIM-023) ───────────────────────────────
    'backups' => [
        'retencion_dias' => (int) env('REM_BACKUP_RETENCION', 30),
        'alerta_falla' => true,
    ],

    // ── Rutas de trabajo en storage (PLAN §2 árbol) ──────────────────
    'storage' => [
        'poc' => 'rem/poc',
        'migracion' => 'rem/migracion',
        'generador' => 'rem/generador',
        'fixtures' => 'rem/fixtures',
    ],
];
