# WebCargaREM — PLAN Técnico del MVP

**Versión:** 1.0
**Fecha:** 2026-10-26
**Estado:** Propuesto para aprobación
**Fuentes:** `specs/AGENT.md` (contexto y restricciones), `specs/SPECS.md` (requisitos funcionales)
**Alcance de este documento:** decisiones técnicas, arquitectura, modelo de datos, módulos, flujos, seguridad, pruebas y secuencia de implementación del MVP. NO contiene: código de implementación, ni inventario exhaustivo de tareas atómicas (eso vive en `specs/TASK.md`).

---

## 1. Resumen técnico

### 1.1 Estrategia general

WebCargaREM se construye como **una única aplicación Laravel 12 con dos paneles Filament 4** (panel interno en intranet, portal en HTTPS), un **núcleo de dominio compartido** entre ambos, persistencia en **MySQL/MariaDB (XAMPP)** y generación del archivo Minsal **exclusivamente en Windows vía COM/ADODB + ACE OLEDB 12.0**.

Las decisiones estratégicas que responden directamente a los requisitos:

| # | Decisión estratégica | Responde a |
|---|---|---|
| 1. | **Reglas como datos, no como código**: el motor de validación es un intérprete genérico manejado por `cat_diccionario`, `cat_thojas`, `cat_excep`, `cat_colexcep`, `cat_versiones` (MySQL). Cambiar una regla = actualizar una fila. | OBJ-04, RN-005, RN-009–RN-020 |
| 2. | **Una tabla unificada `datos` con columna `serie`** (en lugar de un .mdb por serie×año) con índices compuestos. | OBJ-01, FUERA-004, modelo §8 de SPECS |
| 3. | **Validación en cola**: el request HTTP solo guarda el archivo y encola un job; el worker valida y deja el resultado listo. La UI nunca se bloquea. | RF-003, RNF-003, RNF-004 |
| 4. | **Dos paneles, un solo núcleo**: comparten modelos, servicios, BD y reglas; se separan por rutas, permisos (Shield) y exposición de red (dos vhosts Apache). Aislamiento por `establecimiento_id`. | OBJ-02, RN-001, RN-015, RN-016 |
| 5. | **Paridad verificable**: suite de pruebas de paridad contra ValidadorRem como criterio de cierre del motor de validación y de la generación .zip. | OBJ-04, OBJ-05, RNF-018, CA-M1-EXTRA, CA-014 |
| 6. | **Cadena de integración Minsal con 3 niveles de respaldo** (GET/XML → SOAP → importador Global.mdb), transaccional y auditada. | OBJ-06, RN-019 |
| 7. | **Construcción incremental por hitos con verificación temprana de contratos técnicos de riesgo** (COM/ACE, SOAP, extensiones PHP) antes de construir encima. | FASE-0, LIM-019, PEND-001 |
| 8. | **Preparado para multi-servicio**: todas las tablas operativas llevan `idservicio` (filtrado global configurable), aunque día 1 opera solo Osorno (23). | OBJ-08, RF-021 |

### 1.2 Principios no negociables (derivados de AGENT.md §9)

- Paridad exacta de reglas de validación y formato de salida Minsal (no "equivalente": idéntico).
- Consolidación solo tras aceptación explícita del equipo REM; nunca automática (RN-013).
- Panel interno jamás expuesto a internet (RN-015).
- Sin SQL concatenado; 100% Eloquent/Query Builder parametrizado y transaccional.
- Sin sobre-ingeniería: no DDD completo, no microservicios, no particionado (FUERA-004).

---

## 2. Decisiones y tecnologías

### 2.1 Tecnologías confirmadas (no reabrir — tomadas de AGENT.md §8)

| ID | Tecnología | Propósito | Justificación | Restricciones |
|---|---|---|---|---|
| TEC-01 | **Laravel 12** (PHP 8.3+) | Framework de aplicación | Ecosistema maduro, colas, scheduler, validación, ORM parametrizado nativo | Requiere `com_dotnet` y `soap` habilitados en php.ini del XAMPP servidor (pendiente de verificar en H0 — LIM-019) |
| TEC-02 | **Filament 4 (dos paneles)** | UI de ambos portales | Admin panels con recursos, policies, acciones; elimina desarrollo de CRUD manual | Filament 4 es reciente: fijar versiones exactas en `composer.lock` y verificar compatibilidad de plugins (ver RIES-11) |
| TEC-03 | **MySQL/MariaDB (XAMPP)** | Persistencia | Ya instalada; consultas parametrizadas nativas; InnoDB con transacciones | Sin particionado (FUERA-004); ~2,3M filas en `datos` manejables con índices |
| TEC-04 | **PhpSpreadsheet** (`setReadDataOnly(true)`) | Lectura Excel .xls/.xlsx/.xlsm | Lee los 3 formatos por año (RN-003/RN-004); `dataOnly` garantiza valores cacheados (LIM-001) | Archivos grandes: usar ReadFilter por rangos de Thojas para performance (RIES-03) |
| TEC-05 | **Laravel Queues (driver `database`) + worker como tarea programada de Windows** | Jobs de validación, sync, migración, notificaciones | No hay daemon de supervisión nativo en Windows; `queue:work` relanzado por `Task Scheduler` con guard `SkipIfRunning` es el patrón estándar Laravel en Windows | Worker con `--max-runtime` y reinicio cíclico; monitoreo de cola desatendida en dashboard Admin (RIES-04) |
| TEC-06 | **COM/ADODB + ACE OLEDB 12.0 (via `com_dotnet`)** | Generar y leer archivos .mdb | Único camino viable en PHP-Windows para replicar salida Minsal (ya instalado — lo usa ValidadorRem) | Requiere pruebas en H0 (RF-014 depende; LIM-019); la app falla con error crítico claro si el proveedor no está |
| TEC-07 | **ZipArchive + GZip (PHP nativo)** | Empaquetar .mdb → .zip Minsal | Output byte-compatible con el .zip actual de ValidadorRem | Nivel de compresión y cabeceras GZip replicadas exactas (paridad CA-014) |
| TEC-08 | **Guzzle + SimpleXML (primario) / SoapClient (respaldo) / importador .mdb (último recurso)** | Integración Minsal | Endpoints GET/XML verificados operativos; SOAP legacy 2015 operativo como respaldo | Timeouts y reintentos explícitos; caché del último sync exitoso (LIM-017) |
| TEC-09 | **maatwebsite/laravel-excel** | Exportación Excel de informes | Estándar Laravel; replica matrices EstabCargados e informes | Filtrar versiones compatibles con Laravel 12 |
| TEC-10 | **Filament Shield** | Roles (4) y permisos por recurso/policy | Genera policies alineadas con ROL-OPERADOR/SUPERVISOR/ADMIN/ESTABLECIMIENTO | Verificar Shock compatibilidad Filament 4 |
| TEC-11 | **spatie/laravel-activitylog** | Auditoría (RF-023, RNF-012) | Registro estructurado: causer, IP, propiedades, timestamps | Logs en tabla MySQL rotada por retenión configurable |
| TEC-12 | **Laravel Mail (SMTP institucional) + extensión FTP de PHP** | Notificaciones RF-033 | Canal confirmado por usuario (PEND-003): FTP; correo también disponible | Credenciales FTP/SMTP en `.env` (PEND-007); diseño desacoplado por canal via `NotificationChannel` |
| TEC-13 | **Disco local fuera del webroot** (`storage/rem/archivos`) | Archivos subidos | Retención indefinida (RN-017), no descargables directamente por URL | Hash SHA-256 al guardar/registrar; ruta nomenclada `{año}/{mes}/{establecimiento}/{serie}/` |
| TEC-14 | **Pest/PHPUnit + suite de paridad** | Pruebas | Verificación de reglas y salida Minsal contra datos reales ValidadorRem (PEND-005 resuelto: crear suite con datos reales) | Requiere conjunto de Excel reales 2024–2026 y .mdb de referencia bajo directorio de fixtures |

### 2.2 Decisiones arquitecturales del plan (propuestas para aprobación)

| ID | Decisión | Propuesta | Alternativa descartada / razón |
|---|---|---|---|
| TEC-15 | **Autenticación y MFA** | Auth nativo Laravel + TOTP con paquete maduro compatible con Laravel/Filament 4 (p. ej. una de las librerías TOTP estándar de la comunidad). Middleware `EnforceMfa` en producción para ADMIN y SUPERVISOR; opcional en desarrollo (RN-014, LIM-022). | AWS Cognito / SSO — innecesario para tamaño del MVP y rango cerrado de usuarios |
| TEC-16 | **Usuarios: un solo `users` + rol y `establecimiento_id`** | Una tabla `users` con `rol` (operador/supervisor/admin/establecimiento), `establecimiento_id` nullable, `tipo_cuenta` (titular/suplente|null), `mfa_*`. Ventaja: auth, Shield, activity log y recovery trabajan sobre una sola entidad; RN-002 se aplica como constraint/policy de máx. 2 cuentas activas por `establecimiento_id`. | Dos tablas separadas (UsuarioInterno / CuentaEstablecimiento): duplicaría auth, policies y Shield;Audio no aporta trazabilidad extra |
| TEC-17 | **Recepciones con versionado por filas hijas** | `recepciones` (grupo estab+mes+año+serie, única) + `recepcion_versiones` (cada envío: version, archivo, estado, en_plazo, es_segunda_subida, carga_id). El estado vigente de la recepción se deriva de su última versión. Cumple exactamente RN-056/RN-057/RN-011 con historial natural | Fila nueva de `recepciones` por versión con autorreferencia: complica consultas de matriz (RF-006/RF-007) y duplica lógica de "última versión" |
| TEC-18 | **Programador (scheduler)** | `artisan schedule:run` vía Windows Task Scheduler cada minuto (patrón estándar Laravel on Windows); programación declarativa en `routes/console.php` (recordatorio día 3 hábil, backups, monitoreo de cola, sync feriados). | Cron emulado por librerias de terceros: sobrecarga innecesaria |
| TEC-19 | **API de feriados** | **feriadosapp.cl / api.feriados.cl REST GET por año**, tolerante a fallo con precarga manual editable (RN-009) y `feriados` como tabla MySQL cacheada. Job anual descarga y siembra; admin edita a mano. | Web service gob.cl SOAP — menos amigable; decisión pendiente PEND-006 |
| TEC-20 | **Aislamiento multi-servicio** | `ScopeServicioActivo` aplicado a todos los modelos operativos vía servicio `ServicioActivo` (tabla `seteo` equivalente, editable — RF-021); policies de Shield combinan rol + establecimiento (RN-001). | Multi-tenancy de Filament: innecesario con un solo servicio activo |

### 2.3 Decisiones pendientes (no se presentan como confirmadas)

| ID | Decisión | Impacto | Origen |
|---|---|---|---|
| PD-PLAN-01 | Librería TOTP concreta (validar compat Filament 4/Laravel 12 en FASE-1) | RF-017, RN-014 | este plan |
| PD-PLAN-02 | API feriados definitiva y formato de respuesta | RF-019, RF-026, RF-033 | PEND-006 |
| PD-PLAN-03 | Servidor/estructura FTP para recordatorios y comprobantes | RF-033 | PEND-007 |
| PD-PLAN-04 | Disponibilidad de WAF delante del portal | RN-016, CA-SEG-04 | AGENT §10 preg. infraestructura |
| PD-PLAN-05 | Tipos DECIMAL exactos para columnas `col01..col50` (holes y escala) y tipo de `Mes` (VARCHAR vs TINYINT) alineados con tipos reales del .mdb Minsal | RF-014, paridad | derivado de PEND-010 (validar con ACE en FASE-0) |
| PD-PLAN-06 | Mes límite para campo `Registros.Mes` en generación | RF-014 | PEND-009 |
| PD-PLAN-07 | Lista definitiva de 86 establecimientos y series por cada uno (siembra `establecimiento_series`) | RF-020, migración | PEND-008 |

Ninguna otra tecnología fuera de estas tablas; no se agregan dependencias por preferencia.

---

## 3. Arquitectura general

### 3.1 Capas (un único proceso Laravel; separación lógica, sin microservicios)

```
┌────────────────────────────────────────────────────────────────────────┐
│ RED                                                                    │
│  Intranet (vhost interno)          Internet HTTPS (vhost subdominio)  │
└───────────────┬─────────────────────────────────────┬──────────────────┘
                │                                     │
        ┌───────▼────────┐                   ┌────────▼────────┐
        │ Panel interno  │                   │ Portal recepción│   ← Capa HTTP (Filament 4)
        │ (Backoffice    │                   │ (PortalPanel)   │      Filament Resources/Pages +
        │  Panel)        │                   │ FilamentPanel   │      Livewire por panel
        └───────┬────────┘                   └────────┬────────┘      Policies Shield
                │                                     │
        ┌───────▼─────────────────────────────────────▼────────┐
        │ CAPA DE SERVICIOS / DOMINIO (compartida por ambos)   │
        │  MotorValidacion · Consolidador · VentanaService ·   │      ← Servicios concretos (POPO PHP)
        │  GeneradorMinsal · MinsalIntegrador · MigradorRem ·  │      Contratos de interfaces + DTOs
        │  NotificationChannel · HabilCalculator · StorageX    │
        └───────┬───────────────────────┬──────────────────────┘
                │ (encola / consume)    │ (usa)
        ┌───────▼────────┐      ┌───────▼──────────────────────────┐
        │ CAPA COLAS     │      │ CAPA INTEGRACIÓN                 │      ← Jobs (Laravel database queue),
        │ ValidarJob ·   │      │  MinsalXmlClient · MinsalSoap-   │        workers vía Task Scheduler,
        │ NotificarJob · │      │  Client · MdbAccess.Reader/Writer│        COM/ADODB+ACE (com_dotnet),
        │ SyncJob ·      │      │  FeriadosClient · FtpUploader ·  │        Guzzle/SimpleXML, SoapClient,
        │ BackupJob ·    │      │  ExcelReader(PhpSpreadsheet)     │        PhpSpreadsheet, ZipArchive
        │ ReminderJob    │      └───────┬──────────────────────────┘
        └───────┬────────┘              │
                │                       │
        ┌───────▼───────────────────────▼──────────────────────────┐
        │ CAPA PERSISTENCIA                                          │
        │  Eloquent ORM (Modelos DAT-01..DAT-24, ScopeServicio)      │      ← MySQL InnoDB + activitylog
        │  Transacciones RN-021 · índices compuestos · backups       │
        └──────────────────────────────────────────────────────────┘
                │
        ┌───────▼────────┐
        │ STORAGE        │  storage/rem/archivos (fuera del webroot)
        │  .xls/xlsx/xlsm│  + .mdb intermedio de generación + copias seguras
        └────────────────┘
```

### 3.2 Límites de responsabilidad (sin duplicar)

- **HTTP/Filament:** solo recopila entrada, mutex de UI, muestra resultados. **No contiene reglas de negocio.**
- **Servicios de dominio:** reglas de negocio (validación, consolidación, ventanas, versiones, permisos de subida, paridad).
- **Jobs:** orquestan servicios de dominio en background; no implementan reglas.
- **Integración:** sabe hablar con protocolos externos (Excel, mdb, XML, SOAP, FTP, API feriados); produce/gestiona DTOs puros.
- **Modelos:** relaciones, scopes, attributes; **sin lógica de negocio compleja**.

### 3.3 Mecanismos de comunicación

| Mecanismo | Cuándo | Tecnología |
|---|---|---|
| Síncrono HTTP | Login, navegación, subir archivo → guardar + encolar | Livewire (Filament) |
| Asincrónico (cola BD) | Validar archivo, sync Minsal, descargar histórico, backup, notificaciones | Laravel Queues driver `database` |
| Programado | Recordatorio día 3 hábil, sync feriados anual, backups diarios, health-check de worker | `schedule:run` via Task Scheduler |
| Audit log | Toda acción crítica (RNF-012) | spatie/activitylog (síncrono al servicio) |

### 3.4 Separación de red (RN-015/RN-016)

- **Vhost A (intranet):** `BackofficePanel::login()` en `/backoffice/*`. Apache `Require ip <rango-intranet>` (o VPN).
- **Vhost B (portal HTTPS subdominio):** `PortalPanel::login()` en `/portal/*`. HSTS + redirección a HTTPS. **Rewrite que niega acceso a `/backoffice/*` desde este vhost.**
- **Rate limiting por vhost:** en el vhost del portal (Throttle por IP+usuario para login y subidas); adicional throttle Laravel en rutas del portal.

---

## 4. Estructura del proyecto

Árbol propuesto dentro del repositorio actual (raíz = proyecto Laravel; los documentos `specs/` viven junto a la app). **No se crean aquí archivos reales en este paso; es solo el diseño de estructura.**

```
CargasREM/
├── app/
│   ├── Console/Commands/
│   │   └── rem/MigrarCommand.php           # rem:migrar (RF-024)
│   ├── Filament/
│   │   ├── Backoffice/                      # Panel interno (intranet)
│   │   │   ├── BackofficePanel.php          # registro del panel, grupos menú RN-023
│   │   │   ├── Resources/
│   │   │   │   ├── CargasResource.php        # RF-001..RF-005
│   │   │   │   ├── RecepcionesResource.php   # revisión equipo REM (RF-031, acceptar/observar/rechazar)
│   │   │   │   ├── InformesResource (Pages)  # matriz, control, errores, comparativos, duplicidad, auditorías (RF-006..RF-013)
│   │   │   │   ├── MinsalResource (Pages)    # RF-014, RF-015, RF-016
│   │   │   │   ├── VentanaResource, UsuarioResource, CuentaEstablecimientoResource, ServicioResource, SerieResource (RF-017..RF-022)
│   │   │   │   └── BackupsResource + ActivityResource (RF-023)
│   │   │   ├── Pages/  (ResultadosCarga: tabs correctos/errores/control; etc.)
│   │   │   └── Widgets/ (DashboardCards, MatrizCumplimiento — RF-006)
│   │   └── Portal/                          # Portal establecimientos (HTTPS)
│   │       ├── PortalPanel.php
│   │       ├── Pages/ (Calendario, CargarSeries, ResultadoValidacion, Historial, Cumplimiento, Observaciones)
│   │       └── Widgets/ (CumplimientoPropio)
│   ├── Jobs/
│   │   ├── ValidarCargaJob.php              # RF-003 / RF-028 (motor, background)
│   │   ├── SincronizarCatalogosJob.php      # RF-015
│   │   ├── DescargarHistoricoJob.php        # RF-016
│   │   ├── NotificarRecordatorioJob.php     # RF-033a
│   │   ├── NotificarConfirmacionJob.php     # RF-033b
│   │   ├── BackupDatabaseJob.php            # RNF-010
│   │   └── SincronizarFeriadosJob.php       # RN-009
│   ├── Models/                              # DAT-01..DAT-24 (Eloquent + scopes)
│   ├── Policies/                            # Shield-generated + custom RN-001/RN-002
│   ├── Services/
│   │   ├── Rem/
│   │   │   ├── LectorExcelService.php        # PhpSpreadsheet dataOnly + ReadFilter
│   │   │   ├── MotorValidacion/
│   │   │   │   ├── ValidadorEncabezado.php    # RN-009..RN-013
│   │   │   │   ├── ValidadorHojas.php         # RN-014..RN-017 (Thojas/Excep/colexcep)
│   │   │   │   ├── ValidadorNumericos.php     # RN-018, RN-020, LIM-008..LIM-010
│   │   │   │   └── ValidadorControl.php       # RN-019
│   │   │   ├── ConsolidadorService.php       # RF-005, RN-006/RN-007 (transacción)
│   │   │   ├── ArchivoService.php            # guardar/hash/listar/borrar (TEC-13)
│   │   │   └── ParidadService.php            # comparer correctos/errores/control (testing helper)
│   │   ├── Minsal/
│   │   │   ├── CatalogSourceInterface.php
│   │   │   ├── CatalogXmlSource.php          # RN-029 (primario)
│   │   │   ├── CatalogSoapSource.php         # RN-030 (respaldo)
│   │   │   ├── CatalogMdbSource.php          # RN-031 (último recurso; también siembra FASE-1 y migración)
│   │   │   ├── CatalogSyncService.php        # RF-015 (transacción por tabla, progreso, auditoría)
│   │   │   ├── HistoricoDownloaderService.php# RF-016
│   │   │   ├── ComMdbAdapter.php             # TEC-06 wrapper COM/ADODB (writer/reader)
│   │   │   └── GeneradorZipMinsalService.php # RF-014 (RN-025..RN-028)
│   │   ├── Calendario/
│   │   │   ├── FeriadosClient.php            # RN-009/RNF-020
│   │   │   ├── HabilCalculator.php           # días hábiles, día 3 hábil
│   │   │   └── VentanaService.php            # RF-019/RF-026 estado ventana, apertura/cierre/prórroga
│   │   ├── Recibidos/
│   │   │   ├── RecepcionService.php          # crear recepción/versión, RN-053..RN-060, RN-011
│   │   │   └── ObservacionService.php        # RF-031, RN-061/RN-062
│   │   ├── Notificaciones/
│   │   │   ├── NotificationRouter.php        # canal SMTP/FTP configurable (RN-018/RN-063)
│   │   │   └── FtpUploader.php / MailTemplates
│   │   ├── Seguridad/
│   │   │   ├── ServicioActivoService.php     # RF-021
│   │   │   └── AislamientoScope.php          # RN-001 global scope
│   │   └── Soporte/ (RespaldoService mysqldump, HealthCheckService, Contadores)
│   ├── Support/  (DTOs: ResultadoValidacion, ErrorCelda, EstadoCarga enum, EstadoRecepcion enum)
│   └── Providers/ (AppServiceProvider, Filament panels, EventServiceProvider audit hooks)
├── bootstrap/, config/ (config/rem.php: idservicio, canales, límites série, timeouts Minsal, ftp/smtp)
├── database/ (migrations DAT-*, seeders: ServiciosSeeder, FeriadosSeeder2025-2027, SeriesSeeder, AdminSeeder)
├── lang/es/rem.php   (todos los mensajes es-CL, err LIM-RN catalogados RNF-016)
├── resources/views/ (emails plantillas, tema Osorno overrides Filament)
├── routes/ (web.php, console.php — programación TEC-18)
├── storage/rem/
│   ├── archivos/{año}/{mes}/{establecimiento_id}/{serie}/[version]/  # subidas intocadas TEC-13
│   ├── minsal/{año}/{serie}/   (mdb intermed temporal borrado, zip final)
│   ├── migracion/            (copias de trabajo de BD/ — originales intocados y hash verificado; BD/ es local, no versionada: docs/BD-LOCAL.md)
│   └── backups/              (dumps MySQL, respaldo archivos)
├── tests/
│   ├── Unit/ (motor, reglas RN-009..RN-020 con tablas de casos)
│   ├── Feature/ (Filament flows servicios, portal, policies, MFA)
│   ├── Parity/
│   │   ├── fixtures/ (excels reales + mdb dump + JSON esperado ValidadorRem)
│   │   └── ParidadCargaTest, ParidadZipTest
│   └── CreatesApplication.php
└── specs/ (AGENT.md, SPECS.md, PLAN.md ← este archivo, TASK.md futuro, INFORME.md)
```

Notas:
- `storage/rem/` ya está fuera de webroot por defecto en Laravel (cumpliría LIM-019 de rutas). El vhost apunta a `public/` únicamente.
- `CatalogMdbSource` es reutilizable por: siembra inicial FASE-1, respaldo sync (RN-031), migración M6 (misma tecnología COM/ACE lectura).

---

## 5. Modelo técnico de datos

### 5.1 Modelo conceptual (entidades DAT, conservadas de SPECS §8)

Motor MySQL **InnoDB, charset utf8mb4**, todos los timestamps `created_at/updated_at` salvo catálogos de solo sync. Los IDs internos son `BIGINT UNSIGNED` autoincrement (PK); las claves de negocio (like `establecimiento_id`) se separan de identificadores Minsal originales (`id_minsal`) cuando el catálogo sync los reemplace.

| ID | Entidad (tabla) | Descripción / atributos clave | Relaciones | Requisitos servidos |
|---|---|---|---|---|
| DAT-01 | `servicios` | id (pk, igual a idservicio Minsal: 23), nombre, region, orden | 1→N establecimientos | RN-010, RF-021 |
| DAT-02 | `establecimientos` | idservicio, codigo_minsal, nombre, dirección, teléfono, idcomuna, iddependencia, idtipo, propiedad, activo | N→1 servicios; 1→N users(cuentas), cargas, recepciones | RN-001, RN-010, RF-018 |
| DAT-03 | `users` | Singular para 4 roles. `rol` ENUM, `establecimiento_id` (nullable FK), `tipo_cuenta` ENUM(titular,suplente,NULL), nombre, email unique, password_hash, `mfa_secret` nullable, `mfa_habilitado` bool, activo, intentos_fallidos, bloqueado_hasta | N→1 establecimientos (solo rol establecimento) | RF-017, RF-018, RN-001, RN-002 |
| DAT-04 | `series` | codigo PK char(2) (A,BS,BM,D,P,F), nombre, descripcion | 1→N cat_*, establecimiento_series | RN-013 (mapeo versione) |
| DAT-05 | `establecimiento_series` | establecimiento_id, serie, requerida bool | N→1 establecimientos; N→1 series | RF-020, RN-045(PEND-008 siembra) |
| DAT-06 | `feriados` | fecha date, tipo ENUM(nacional,regional_los_lagos,otro), descripcion, año | — (n por año) | RN-042, RN-050, RNF-020 |
| DAT-07 | `ventanas_carga` | idservicio, año, mes, apertura datetime, cierre datetime, habilitada bool, notas, creada_por | N→1 servicios | RF-019, RN-042–RN-044 |
| DAT-08 | `cargas` | idservicio, año, serie, mes, establecimiento_id, archivo (ruta), hash_sha256, origen ENUM(interno, portal), estado ENUM(pendiente, validando, validada, con_errores, guardado, rechazado), usuario_id, recepcion_version_id (nullable, si origen portal), created/updated | N→1 establecimientos, users, versiones | RF-001..RF-005, RF-027 |
| DAT-09 | `recepciones` | Grupo lógico por (establecimiento_id,serie,mes,año,idservicio) — UNIQUE. Estado vigente derivado. | 1→N versions; N→1 establecimientos | RF-027, RF-032 |
| DAT-10 | `recepcion_versiones` | recepcion_id, version (1..N), archivo, hash, fecha_carga, en_plazo bool, es_segunda_subida bool, estado ENUM(recibida,validando,validada,con_errores,en_revision,aceptada,observada,rechazada), carga_id (nullable→ cuando consolidada), usuario_id | N→1 recepciones; N→1 users; 1→0..1 cargas | RF-027..RF-031, RN-010–RN-012 |
| DAT-11 | `recepcion_observaciones` | recepcion_id, version_cerrada (a quién responde), autor_id, autor_rol, tipo ENUM(observacion,rechazo), texto | N→1 recepciones | RF-031, RN-061, RN-062 |
| DAT-12 | `recepcion_eventos` | recepcion_id, version_id, estado_anterior, estado_nuevo, usuario_id, timestamp | N→1 recepciones | RN-056 trazabilidad, CA-SEG-06 |
| DAT-13 | `datos` (tabla única serie) | idservicio, serie, mes, año, establecimiento_id, codigo_prestacion, fecha_ingreso, `col01..col50`, carga_id (nullable para datos históricos migrados/sync) | N→1 establecimientos; idx (idservicio,serie,año,establecimiento_id,mes); idx(serie,año,codigo_prestacion) | RF-005, RF-014 (RN-026), RF-011 (detect duplicidad) |
| DAT-14 | `datos_control` | idservicio, serie, mes, año, establecimiento_id, col01..col05, carga_id | idem | RF-003 RN-019, RF-014 |
| DAT-15 | `hist_carga` | establecimiento_id, mes, año, serie, idservicio, fuente ENUM(upload,servidor) | idx compuesto | RF-005, RF-016 RN-038 |
| DAT-16 | `log_errores` | carga_id, establecimiento_id, mes, serie, hoja, linea, dato (valor original), año, idservicio, mensaje_id (referencia catálogo de errores) | N→1 cargas | RF-003 RF-004 RF-009 |
| DAT-17 | `cat_diccionario` | codigo_prestacion, mes, año, texto_prestacion, hoja, serie, posicion, tipo_dato, linea, inicio, fin | idx(serie,año,hoja,mes) | RN-015 |
| DAT-18 | `cat_thojas` | serie, hoja, año, mes, rango | idx(serie,año) | RN-014 |
| DAT-19 | `cat_excep` | codigo_prestacion, mes, año, hoja, serie, posicion, tipo_dato, col_excep | idx | RN-016 |
| DAT-20 | `cat_colexcep` | codigo_pres, col | idx | RN-017 |
| DAT-21 | `cat_versiones` | mes, año, version, serie, serie_excel (mapeo SerieExcel↔serie interna) | UNIQUE(serie_excel,año,mes) | RN-013 |
| DAT-22 | `cat_software` | nombre=’local’, version | — | RF-015 RN-029 |
| DAT-23 | `activity_log` (spatie) + `recepcion_eventos` | event, causer, ip, propiedades, timestamp | — | RNF-012, RF-023, CA-SEG-06 |
| DAT-24 | `generaciones_minsal` | año, serie, usuario_id, fecha, tamaño_bytes, hash_sha256, filas_datos, filas_control, resultado ENUM(ok,fail), mensaje | — | RF-014, historial de generaciones (SPECS §7.1 Minsal) |
| DAT-25 | `sincronizaciones` | tipo ENUM(catalogos,historico), fuente ENUM(xml,soap,mdb), comenzado_terminado, tablas_resumen JSON, estado, usuario_id, error | — | RF-015/RF-016, LIM-017 |
| DAT-26 | `notificaciones_enviadas` | usuario_id, establecimiento_id, tipo ENUM(recordatorio,confirmacion), canal ENUM(smtp,ftp), fecha, referencia, resultado | — | RF-033, trazabilidad envíos |

### 5.2 Separación conceptual vs. almacenamiento

- **Conceptual** (SPECS §8) define entidades/estados/relaciones (se mantienen igual).
- **Almacenamiento concreto** (este plan) define: InnoDB + utf8mb4, PKs artificiales, FKs explícitas con ON DELETE RESTRICT (no cascade salvo tablas de resultados por-id para reemplazo RN-022), índices compuestos para consultas de informes y Minsal, columnas DECIMAL con precisión adecuada (ver PD-PLAN-05) y capa de `$casts`/enums en PHP.

### 5.3 Estados (like SPECS §7.1)

```
cargas:          pendiente → validando → validada ┐ valida sin errores ┐
                                    └→ con_errores ┴→ guardado | rechazado

recepcion_versiones:
   recibida → validando → validada | con_errores → en_revision → aceptada | observada | rechazada
   (+ flags: en_plazo=false, es_segunda_subida=true en una única v2 por grupo — RN-010/RN-058)
```
Estados alineados 1:1 con SPECS; derivación del estado de cabecera recepciones: `estado = estado de la última versión`.

### 5.4 Persistencia de consolidación (RF-005 / RN-006/RN-007)

Transacción única por consolidación:
1. Si reemplazo: `DELETE` en `datos`, `datos_control`, `log_errores` de (estab,mes,año,serie del origen).
2. `INSERT ... bulk` en `datos` (correctos) y `datos_control` (control) en chunks (p. ej. 1000 filas) dentro de la transacción.
3. `UPSERT hist_carga`.
4. Actualiza `log_errores` de la carga (ya insertado por job, se conserva por auditoría o se limpia según RN-007).
5. Actualiza `cargas.estado=guardado` (o `recepcion_versiones.estado=aceptada` + vínculo carga).

Rollback en cualquier error.

---

## 6. Módulos y componentes

Cada módulo se verifica con los requisitos y criterios que satisface. IDs conservados en mayúscula para cross-referencing.

### MOD-01 — Modelos y acceso a datos (framework)
- **Responsabilidad:** representar DAT-01..DAT-26 con Eloquent + scopes (servicio activo, aislamiento), casts de enums, relaciones e helpers.
- **Requisitos:** base de RF-001..RF-033; RN-001 preliminar.
- **Datos que administra:** todos.
- **Entradas:** llamadas de servicios/resources. **Salidas:** colecciones/objetos.
- **Dependencias:** MySQL (TEC-03), Laravel (TEC-01).
- **Verificación:** migraciones ejecutan `php artisan migrate:fresh --seed` en CI local; tests de modelos (relaciones, scopes, unique constraints).

### MOD-02 — Motor de validación (Reglas como datos)
- **Responsabilidad:** interpretar un archivo Excel ya leído contra catálogos `cat_*` exactamente como ValidadorRem (RN-009..RN-020, RN-005).
- **Requisitos:** RF-003 (interno), RF-028 (portal).
- **Datos:** lee cat_* (solo lectura), escribe resultados en memoria (DTO ResultadoValidacion) que el job persiste.
- **Entradas:** `carga_id` + archivo + catálogos vigentes.
- **Salidas:** `ResultadoValidacion {correctos[], errores[], control[]}` + estado final sugerido.
- **Dependencias:** MOD-03 (LectorExcel), MOD-01 (lectura catálogos), cola (MOD-05).
- **Verificación:** **suite de paridad** (fixtures reales vs resultados válidos ValidadorRem — CA-003, CA-M1-EXTRA) + table-driven tests unitarios por regla (RN-009..RN-020) + LIM-001..LIM-011.

### MOD-03 — Lector Excel (Puente Spreadsheet)
- **Responsabilidad:** abrir .xls/.xlsx/.xlsm con `setReadDataOnly(true)`, aplicar ReadFilter por rangos de `cat_thojas`, exponer valores cacheados con coordenadas (Celda→valor).
- **Requisitos:** RF-001/RF-002/RF-003/RF-027; RN-003/RN-004; LIM-001 (fórmulas sin cache → rechazo), LIM-020 (límites tamaño).
- **Dependencias:** TEC-04, MOD-09 (decide años → formatos).
- **Verificación:** tests con archivos por año (2016/2017/2026) cubriendo las 3 extensiones.

### MOD-04 — Almacenamiento de archivos
- **Responsabilidad:** guardar subidas fuera del webroot, calcular hash SHA-256, nombrar por `{año}/{mes}/{establecimiento}/{serie}/{version}/`, servir descarga autenticada (controller con policy), borrar solo manual Admin (RN-017).
- **Requisitos:** RF-001, RNF-009, RN-017.
- **Verificación:** tests de escritura/lectura/permisos; CA-SEG (no acceso directo por URL).

### MOD-05 — Colas y programación (infraestructura jobs)
- **Responsabilidad:** consumir cola `database` con worker reiniciable vía Task Scheduler (TEC-05), programar schedule:run cada minuto (TEC-18), retries y timeouts por job, monitoreo de cola en dashboard.
- **Requisitos:** RNF-003/RNF-004, RF-003, RF-033, LIM-023.
- **Verificación:** Test feature integra job + assert resultados; health-check dashboard Admin.

### MOD-06 — Carga y validación interna (M1)
- **Responsabilidad:** recursos Filament: subir individual (RF-001), masiva carpeta/zip (RF-002 con RN-006/RN-007/RN-008), pantalla resultados 3 pestañas (RF-004), botón consolidar con confirmación (RF-005 con RN-021/RN-022 y LIM-012).
- **Dependencias:** MOD-02, MOD-03, MOD-04, MOD-05, MOD-01.
- **Verificación:** CA-001..CA-005, CA-M1-EXTRA (50 fixtures reales).

### MOD-07 — Informes y dashboard (M2)
- **Responsabilidad:** dashboard con tarjetas+matriz (RF-006 RN-023/RN-024), matriz Estab×Mes exportable (RF-007), control faltante (RF-008), log errores (RF-009), comparativos (RF-010), duplicidad (RF-011), auditorías Prestacion/Decimales/ValoryText (RF-012), export libre (RF-013).
- **Dependencias:** MOD-01; export vía maatwebsite (TEC-09). Sólo lectura; sin jobs salvo export.
- **Verificación:** CA-006..CA-013 + CA-M2-EXTRA.

### MOD-08 — Generación archivo Minsal (M3)
- **Responsabilidad:** copiar `MASTER.mdb`, volcar `datos` (Col01..Col50), `datos_control` (col01..05) y fila `Registros` (RN-026), comprimir GZip a .zip (RN-027), registrar DAT-24, servir descarga. Requiere datos existentes (RN-025, LIM-018). Job background + download.
- **Dependencias:** MOD-10 (ComMdbAdapter), MOD-01, MOD-05.
- **Verificación:** CA-014 paridad fila-a-fila con Hito H3; CA-M3-EXTRA (subida a portal Minsal de prueba, manual).

### MOD-09 — Integración Minsal (M4)
- **Responsabilidad:** CatalogSyncService (RN-029..RN-033: GET/XML→SOAP→importador mdb; transacción por tabla con borrado+reinsersión; normalización comas→puntos y apóstrofes; progreso; audit); HistoricoDownloaderService (RF-016: RN-034..RN-038 con confirmación previa y limpieza local).
- **Dependencias:** MOD-10, MOD-11, fuentes externas Minsal (TIMEOUT/reintentos).
- **Verificación:** CA-015, CA-016, LIM-017 (cascada de fuentes).

### MOD-10 — Adaptador COM/ADB .mdb (reader/writer)
- **Responsabilidad:** wrapper único (única instancia de lógica COM/ADODB+ACE en todo el código) con métodos `open`, `getQuery`, `bulkInsert`, `close`, timeout y mensajes claros en falla (LIM-019). Usado por MOD-08 (escritura), MOD-09/MOD-13 (lectura e insercción).
- **Dependencias:** TEC-06 (`com_dotnet`).
- **Verificación:** POC en FASE-0 (go/no-go); tests de integración con .mdb pequeño real.

### MOD-11 — Administración (M5)
- **Responsabilidad:** usuarios internos (RF-017), cuentas establecimiento 2 nominales (RF-018, RN-002, RN-039..RN-041), ventanas de carga híbridas + feriados (RF-019, RN-042..RN-044), series por establecimiento (RF-020), servicio activo confirmable (RF-021), tema visual Osorno (RF-022), auditoría+backups+estado sistema (RF-023).
- **Dependencias:** MOD-01, MOD-02 (lectura cat log), MOD-16 (roles), MOD-12 (feriados), TEC-10, TEC-11.
- **Verificación:** CA-017..CA-023, CA-M5-EXTRA.

### MOD-12 — Calendario y feriados
- **Responsabilidad:** Cliente API feriados (precargado anual + editable manual), cálculo días hábiles (RN-042/RN-050), cálculo "día 3 hábil" (RN-064), estado ventana vigente por establecimiento (RN-044/RN-051/RN-052).
- **Dependencias:** DAT-06/DAT-07, servicio externo API (tolerante a fallo, precarga fallback).
- **Verificación:** tests calendario 2026 con feriados conocidos; CA-019, CA-026.

### MOD-13 — Migración histórica (M6)
- **Responsabilidad:** comando `rem:migrar --desde --hasta` (RF-024): pasos catálogos→datos/control por serie/año→reconstrucción hist_carga→siembra establecimiento_series; idempotente (RN-047), verificación conteos (RN-049), cruza catálogos con establecimientos del servicio (RN-010). Progreso en log, continúa si falta mdb (LIM-024).
- **Dependencias:** MOD-10 (lector mdb), MOD-01, catálogos presentes (seed inicial FASE-1 o sync).
- **Verificación:** CA-024, CA-M6-EXTRA (re-ejecución no duplica).

### MOD-14 — Portal de recepción (M7)
- **Responsabilidad:** panel Filament portal: login con rate limit + bloqueo (RF-025), calendario (RF-026), carga de series asignadas con validación condicional de ventana (RF-027), resultado validación inmediata (RF-028 con polling de estado), reenvío versión (RF-029), segunda subida única (RF-030), observaciones (RF-031), historial y cumplimiento propio (RF-032).
- **Dependencias:** MOD-02, MOD-03, MOD-04, MOD-05, MOD-12, MOD-15, MOD-16, MOD-17.
- **Verificación:** CA-025..CA-032, CA-M7-EXTRA (flujo completo), CA-SEG-01 (aislamiento), LIM-013..LIM-016, LIM-021.

### MOD-15 — Notificaciones
- **Responsabilidad:** router por canal configurado (SMTP mail / FTP): confirmación post-carga inmediata (RF-033b) y recordatorio día 3 hábil (RF-033a vía job programado y HabilCalculator). Generación de comprobante PDF? — **No**: comprobantes como texto en mail + registro DAT-26; remite indicativo de resultado.
- **Dependencias:** MOD-12 (fecha cálculo), DAT-26, TEC-12 (SMTP+FTP), MOD-05.
- **Verificación:** CA-033; tests unitarios de contenido + mail fake; integración FTP con servidor sandbox (PEND-007).

### MOD-16 — Seguridad y auditoría transversal
- **Responsabilidad:** roles+permisos Shield (TEC-10) sobre 4 ROL-*, policies RN-001/RN-002, MFA TOTP Enforce ( producción), rate limiting, activitylog (TEC-11) en acciones críticas listadas CA-SEG-06, panel interno solo intranet (Apache permitir + bloqueo en vhost público).
- **Verificación:** CA-SEG-01..CA-SEG-06, RNF-012, RNF-017.

### MOD-17 — Backups
- **Responsabilidad:** mysqldump diario programado con retención 30 días + copia de directorio archivos; registro de resultado, alerta dashboard en falla (LIM-023); documento de restore probado trimestralmente (RNF-010/RNF-011, CA-NF-03).

### MOD-18 — Tema visual Osorno
- **Responsabilidad:** paleta institucional (colores, logo) via Filament theming (CSS custom + font asset), dark/light native (SPECS §7.2), aplicado a ambos paneles con mínima divergencia.
- **Verificación:** CA-022, CA-M5-EXTRA.

---

## 7. Lógica y operaciones (contratos de servicios — sin implementación)

Los nombres siguen las convenciones Laravel (Servicios con sufijo `Service`, Jobs con sufijo `Job`, Command con verb).

| Operación | Responsabilidad | Entradas clave | Salida | Errores previsibles | Dependencias |
|---|---|---|---|---|---|
| `MotorValidacion::procesar(cargaOrVersion): ResultadoValidacion` | Ejecutar 5 fases: (1) encabezado RN-009..RN-013, (2) recorrer hojas según cat thojas RN-014, (3) posicionar prestaciones con cat diccionario RN-015, (4) aplicar excep/colexcep RN-016/RN-017, (5) reglas numéricas + filas vacías + control (RN-018..RN-020) | carga_id (or recepcion_version_id), archivo bytes | ResultadoValidacion {correctos, errores[{hoja,línea,columna,valor,mensaje_id}], control[], resumen} | LIM-001..LIM-006, LIM-008..LIM-011 (errores por celda), ErrorCrítico si archivo ílegible | MOD-03 LectorExcel; catálogos sincronizados (MOD-09 o siembra inicial) |
| `ConsolidadorService::guardar(carga, reemplazar:bool): void` | RF-005 transacción RN-021/RN-022 | carga_id validada | estado guardado | Duplicidad por concurrencia (lock últimas versiones), SQL fallas → rollback | MOD-01, MOD-02 |
| `GeneradorZipMinsalService::generar(año, serie): ArchivoGenerado` | RF-014 RN-025..RN-028 (Copiar MASTER.mdb → escribir via ComMdbAdapter 3 tablas → GZip→zip) | año, serie, usuario | DAT-24 registro + path zip | LIM-018 (sin datos), LIM-019 (COM failure), timeout | MOD-10, TEC-06 |
| `CatalogSyncService::sincronizar(): SyncResumen` | RF-015 RN-029..RN-033: probar XmlSource→SoapSource→MdbSource (por esa cascada) para cada tabla cat_*; una transacción por tabla, borrado+reinserción, normalizar valores ("," → ".", escapar apóstrofes), actualizar cat_software.version, registrar DAT-25 | — | SyncResumen{tabla: ok/fail/nFilas} | LIM-017 cascada agotada → estado fail parcial + mantener datos previos | MOD-10, Guzzle/SimpleXML/SoapClient |
| `HistoricoDownloaderService::descargar(serie, año): Resumen` | RF-016 RN-034..RN-038 | serie, año, idservicio | conteos + hist_carga reconstruido | confirmación previa obligatoria (UI), fallas de red → mantener local y auditar | MOD-09 fuentes |
| `MigrarCommand::handle()` | RF-024 RN-047..RN-049 | --desde,--hasta,ruta copias | reporte conteos por serie/año/estab + faltantes (LIM-024) | idempotencia: reuse catálogos seriales (por serie/año/establecimiento fila) antes de insertar; falta de catálogo base aborta | MOD-10, DAT-01..26 |
| `VentanaService::estadoVigente(hoy): {abierta,apertura,cierre,proxima}` | RF-019/RF-026/RF-027 | fecha actual, establecimiento | estado | Ninguno interno (datos previos del admin) | MOD-12 feriados |
| `HabilCalculator::diasHabiles(año,mes): Collection<int>` • `diaNHabil(mes): Date` | RN-042..RN-044, RN-064 | feriados (DAT-06), mes | lista fechas, o fecha del N-ésimo hábil | NA | MOD-12 |
| `RecepcionService::crear(recepcion, serie, archivo): {version, enPlazo, segundaSubida?}` | RF-027..RF-030, RN-053..RN-060, RN-010, RN-011/RN-058 | usario (establecimiento), serie, archivo, ventana | versión creada + carga encolada | LIM-013..LIM-015, LIM-021RN | MOD-12 ventana, RN-001 policy, MOD-02 |
| `RecepcionService::reenviar(recepcionId, archivo): Version` | RF-029, RF-030 (control segunda subida única) | recepcion_id | nueva versión (v+1) | LIM-014 (3ra subida bloqueado), LIM-016 (cierre durante) | MOD-12 |
| `ObservacionService::observar(recepcionId, texto, tipo)` | RF-031, RN-061/RN-062 | recepcion_id, texto, tipo | observación guardada + estado versión observada | — | RN-013 policy equipo REM |
| `NotificationRouter::confirmar(version)` / `recordatorio(dia3)` | RF-033, RN-063..RN-065 | datos de versión/pendientes | DAT-26 registro | canal inactivo→cola+log falla | MOD-12, TEC-12 |
| `ArchivoService::guardar(archivo, contexto): {ruta,hash}` | RF-001 etc. | UploadedFile | ruta + hash | LIM-020 | TEC-13 |
| `Seguridad/ScopeServicio::aplicar($query)` |(idservicio global) todos los modelos operativos) | — | — | — | MOD-01 |

Parámetros y nombres son contractuales pero **no** actúan como implementación anticipada; TASK.md podrá refinarlos.

---

## 8. Flujos técnicos (mapeo FLUJO-01..FLUJO-09 a componentes)

| Flujo SPECS | Secuencia de módulos |
|---|---|
| FLUJO-01 Carga interna | MOD-06 (upload) → MOD-04 → MOD-05 encola → **MOD-02 valida** (usa MOD-03) → persistencia resultados → MOD-06 mostrar 3 pestañas (RF-004) → MOD-06 consolidar via MOD-01 transacción → datos listos para MOD-07/MOD-08 |
| FLUJO-02 Carga masiva | MOD-06bulk (zip/carpeta) → filtro extensión por año (RN-006/RN-007) → N× (encolar MOD-02) → grilla por resultados → consolidación individual (RF-005) |
| FLUJO-03 Recepción portal | MOD-14 login (MOD-16 MFA/rate) → MOD-12 calendario (vista MOD-14) → MOD-14 formulario condicionado → MOD-14 RecepcionService.crear (RN-053/RN-055, 2da subida controlada) → MOD-05 encola → MOD-02 valida (mismas reglas) → MOD-14 resultado inmediato (polling) → si ok → MOD-06 equipo REM acepta (MOD-14 ObservacionService o aceptar directo) → MOD-01 consolidación → MOD-15 NotificationRouter confirmación post-carga |
| FLUJO-04 Segunda subida | MOD-14 bloquea a 1 (RN-058), marca flags, dispara la validación, **no** consolida (RN-060); flujo de lectura en MOD-14 historial |
| FLUJO-05 Sync catálogos | MOD-09 CatalogSyncService (MOD-10 lector mdb como último recurso) → transacción por tabla → DAT-25 registro → cat_software.version → MOD-11 dashboard |
| FLUJO-06 Descarga histórico | MOD-09 HistoricoDownloader con confirmación UI → limpieza local → reinsersión normalizada → reconstrucción hist_carga → auditoría |
| FLUJO-07 Gestión ventana | MOD-11 VentanaResource → MOD-12 FeriadosClient precarga → MOD-11 editar/abrir/cerrar/prorrogar (auditado) → MOD-14 refleja calendario institucional |
| FLUJO-08 Generación Minsal | MOD-11/14 selector año+serie → MOD-08 GeneradorZip (MOD-10 ComMdbAdapter) → DAT-24 → descarga con policy rol |
| FLUJO-09 Recordatorio día 3 hábil | Scheduler (TEC-18) → MOD-12 HabilCalculator diaNHabil(3) → MOD-05 NotificarRecordatorioJob → calcula pendientes (RN-053 series requeridas sin validada/aceptada) → MOD-15 NotificationRouter → FTP/SMTP → DAT-26 |

---

## 9. Interfaz y navegación

### 9.1 Panel interno (Filament BackofficePanel — intranet, desktop-first)

- **Grupos de menú (RN-023):** Carga · Informes · Minsal · Administración · Recepción.
- **Dashboard:** 4 tarjetas StatsWidget (`archivos del mes`, `pendientes`, `errores abiertos`, `fuera de plazo`) + `MatrizCumplimientoWidget` (86×6×mes, agregado desde MOD-01 con filtros serie/mes, export via TEC-09 — RF-006/RN-023/RN-024).
- **Recursos:**
  - `CargasResource` con página de resultados de la carga: `Tabs` 3 secciones (Datos correctos repeater de tabla, Log de errores con filtro, Datos de control) + botón "Consolidar" con modal de confirmación y opción reemplazo (LIM-012). Badges color por estado.
  - `RecepcionesResource` workflow revisión: tabla de versiones de la última versión, detalle incluye hilo de observaciones y versión; acciones "Aceptar" / "Observar" (modal texto obligatorio) / "Rechazar" (RN-013/RN-061).
  - Informes: páginas `InformeMatriz`, `InformeControl`, `InformeErrores`, `Comparativos`, `Duplicidad`, `Auditorias` (3 tipos) + `Export` global FilteringSelect — todos con pílios filtrados y export Excel.
  - `MinsalPages`: selector año/serie + acción "Generar .zip" + tabla DAT-24 historiada.
  - `Integración`: botón Sync (modal progreso tabla por tabla, radio FUENTE si se elige mdb manual) + Descarga histórico (selector y confirmación) + log sincronizaciones.
  - `Administración`: tab-children o recursos separados (verificados con Shield, cada acción auditada).
- **Estados:** badges por estado (los 6 de cargas, los 9 de recepciones SPECS §7.1).

### 9.2 Portal de recepción (PortalPanel — HTTPS, mobile-first)

- Navegación: sidebar fija (Calendario · Cargar · Historial · Cumplimiento) + header con logo Osorno, usuario, dark/light, cerrar sesión (SPECS §7.2).
- **Calendario:** página editable por mes con marcadores de feriados (DAT-06), estado apertura y "Próxima apertura: fecha" (RN-051/RN-052). Invisible por dispositivo según mes vigente.
- **Cargar:** select solo series requeridas de su establecimiento (RN-053), drag&drop archivo, status de validación con polling (`validando → resultado de la versión` en 3 secciones con exactamente hoja/línea/columna/valor), bloqueado con modal si cerrada.
- **Historial:** tabla de cada versión con fecha, versión, estado, en_plazo, 2da subida, resumen (correctos/errores/control) y acción reenviar si permite (dependiente de RN-057/RN-058/RN-060).
- **Cumplimiento:** matriz simple Serie × Mes (✓/✗/⚠) sobre sus propias `recepciones` (RN-001 garantizado por scope/policy, no por UI).
- **Accesibilidad:** RNF-014 WCAG 2.1 AA basic (contrastes tema, labels, foco, touch targets) — verificado con auditoría Lighthouse en FASE-8.
- **Comunicación:** validación de estado con Livewire polling (`wire:poll`) sobre estado de la versión (cache de resultados por versión, idempotente).

### 9.3 Comunicaciones de estado entre capas

- No se usan websockets; polling ligero es suficiente con ventanas de 30–60 s y job típico <30 s (RNF-003).
- Badge global "validaciones en cola" en backoffice para supervisor (colas salud).

---

## 10. Persistencia y consistencia

| Propiedad | Mecanismo |
|---|---|
| **Integridad transaccional** | InnoDB + transacciones explícitas en: consolidación (RN-021), sync catálogos por tabla (RN-032), sync histórico (RF-016), migración por lotes (RN-048). Rollback on any failure. |
| **Concurrencia (consolidación)** | Unique compuesto en `datos` (idservicio, serie, año, mes, establecimiento_id, codigo_prestacion, hoja) para evitar filas duplicadas dentro del mismo envío; para reemplazo (RF-005/LIM-012) se toma **lock de fila** sobre el registro `cargas`/`recepcion_versiones` con `lockForUpdate` antes de delete+insert — garantiza un único consolidador por (estab,mes,año,serie) |
| **Exclusión de filas vacías** | Filtro antes de insert (RN-020/LIM-011 motor). |
| **Versionado recepciones** | DAT-09/10: única por grupo con children de versiones, estado vigente por derivación (Consultas siempre contra las versiones; la cabecera es solo agrupadora). |
| **Idempotencia migración** | RN-047: reejecución con base de clave natural (serie/año/establecimiento/mes) para insert only-inexisting, más reporte de conteos (RN-049). Transacción por serie/año para reanudar si se corta. |
| **Índices** | Ver §5.1 por tabla; índices compuestos alineados a las consultas de informes (RF-007..RF-012) y generación Minsal (por serie/año) — no sobre columnas sin uso. |
| **Retención / limpieza** | Archivos indefinidos con borrado manual Admin (RN-017); activity log con rotación configurable; .mdb intermedio eliminado tras generación (RN-027). |
| **Backups** | mysqldump diario 02:30 → `storage/rem/backups/db`, retención 30 días + Directorio archivos por copia diaria (robocopy rpt). Reconstrucción probada (runbook en TASK). Alerta dashborad en falla (LIM-023). |
| **Recuperación** | Runbook: restaurar dump más reciente + files; verificación de APALIGHT conteo y de `cargas` en cola procesada/reprocesada. |
| **Tiempo** | `timestamps` en UTC internos; fechas presentación en zona `America/Santiago`. |

---

## 11. Validaciones y errores

### 11.1 Reglas → puntos de aplicación

| Regla (SPECS) | Punto técnico de aplicación |
|---|---|
| RN-001 (aislamiento) | Global Scope `AislamientoScope` en modelos de establecimiento + Policies de Shield en recursos portal (MOD-16). Nunca en el form. |
| RN-002 (2 cuentas) | Validación de creación en MOD-11 + constraint lógico (count activo = 2 max) + mensaje LIM. |
| RN-003/RN-004 (extensión por año) | MOD-03/`ArchivoService` + validación filepond en MOD-06/14; en masiva: ignora con estado `Ignorado` (LIM-002). |
| RN-005 (motor idéntico) | MOD-02 con catálogos como única fuente; prohibido hardcodear serie/regla (revisión PR). |
| RN-009..RN-020 | MOD-02 subvalidadores (ValidadorEncabezado/Hojas/Numericos/Control) — ver §6 MOD-02. |
| RN-021/RN-022 (transacción, reemplazo) | MOD-01 `ConsolidadorService` transacción + lockForUpdate. |
| RN-008/RN-042..RN-044 (ventana híbrida) | MOD-12 calendo + corrección de la ventana del mes del formulario; apertura/cierres manuales auditados en activitylog. |
| RN-010..RN-012 (fuera plazo, 2da subida, versionado) | MOD-14/`RecepcionService.reenviar` calcula `en_plazo` al momento (LIM-016); flag de segunda subida y UNIQUE por grupo para máximo 1 segunda subida |
| RN-013 (consolidación solo tras aceptación explícita) | Acción Button disponible solo con permiso de rol, requiere estado válido (`validada` o única v2). Nunca auto on job end (test lo excluye). |
| RN-014 (MFA) | MOD-16 EnforceMfa (solo env production y roles ADMIN/SUPERVISOR); LIM-022 recuperación solo Admin. |
| RN-015/RN-016 (expDOC esión) | Vhosts (§3.4); no es responsabilidad de app PHP (complementar con headers en Apache). |
| RN-019 (cadena sync) | MOD-09 con try en orden; LIM-017 registra fuente fallida. |
| RN-020 (paridad zip) | MOD-08 genera + suite ParityZip (fixture input idéntico a ValidadorRem compare). |
| RN-047 (idempotencia migración) | MOD-13 claves naturales + reporte. |
| RN-018 (notif) | MOD-15: post-carga dispara encola immediate; recordatorio schedule. |

### 11.2 Catálogo de errores es-CL

- Los mensajes de LIM-001..LIM-024 y todos los errores de validación se centralizan en `lang/es/rem.php` con IDs estables (`rem.err.SIN_CACHE`, `rem.err.MES_NO_COINCIDE`, ...), mostrados por código y parámetros, sin añadir strings inline en HTML (RNF-016).
- El motor marca `log_errores` con `mensaje_id` (DAT-16) — así las 3 pestañas RF-004 y el portal RF-028 muestran exactamente los mismos textos y datos.
- El worker registra errores avulsos aparte: encola `failed_jobs` estándar de Laravel y Superáveis reportes en dashboard.
- Auditoría de errores del motor: no loguear datos personales adicionales; solo coordenadas/valor necesario para corregir (RNF-017 minimización).

---

## 12. Seguridad y privacidad

| Área | Medida | Requisito |
|---|---|---|
| Autenticación | Laravel session auth, 2 paneles separados, remember opcional, bloqueo tras N intentos + rate limit (CA-SEG-04: >10/min → 15 min) | RF-025, RF-017, LIM-021 |
| MFA | TOTP opcional por usuario; **obligatorio** en producción para ADMIN/SUPERVISOR (middleware); reset solo Admin | RN-014, CA-SEG-05, LIM-022 |
| Autorización | Shield policies por recurso + custom policies RN-001 (establecimiento) + check de tipo de cuenta; denial default | ROL-*, RN-001/RN-002 |
| Multi-tenant data | Global scope `AislamientoScope` + FK compuestas en consultas (no confiar en UI) | CA-SEG-01 |
| Transporte | Vhost portal: HTTPS obligatorio (HTTP→HTTPS 301, HSTS), ciphers modernos; certificado institucional (PEND-002 resuelto: post-H0). Vhost interno: restricción por IP/VPN | RN-015/RN-016, CA-SEG-02/03 |
| Rate limiting & brute force | Throttle Laravel en login y subidas portal; bloqueo progresivo; WAF si disponible (PD-PLAN-04) | RN-016 |
| Uploads | Whitelist MIME/extension por año, tamaño máximo configurable, guardar siempre fuera webroot, sin ejecución de código, escaneo opcional AV (no en MVP), hash SHA-256 para integridad | LIM-020, TEC-13, RNF-013 |
| SQL injection | 100% Eloquent/Query Builder binding; **prohibido** concatenar SQL (línea base del proyecto) | AGENT §3 |
| CSRF | Protección nativa Laravel Filament | — |
| Secretos | `.env` fuera de repo, HTTPS ftp smtp config, no hardcode; `config/rem.php` lee env; backups también prodosegidos en carpetas con permisos NTFS | AGENT |
| Datos personales | Minimización (solo datos REM + identidades básicas); acceso nominal con activitylog; cumplimenta Ley 19.628/21.719 (RNF-017); borrado de cuentas desactiva en vez de eliminar (trazabilidad) | RNF-017 |
| Auditoría | activitylog en: login/logout, carga, validación[cambio de estado], consolidación, sync, acciones admin, observaciones, cambios ventana, generación zip, backup fail — con causer+IP+timestamp | RNF-012, CA-SEG-06 |
| Headers | X-Content-Type-Options, X-Frame-Options SAMEORIGIN, CSP básics en vhost Apache | — |

---

## 13. Estrategia de pruebas

### 13.1 Niveles

| Nivel | Alcance | Automatización |
|---|---|---|
| **Unit** | Reglas de validación con tablas de casos (por cada RN-009..RN-020 y LIM-001..LIM-011), catálogo mapeos, HabilCalculator (calendarios reales 2025–2027), VentanaService, RecepcionService (flags/versiones/2da subida) | PHPUnit/Pest sí |
| **Integration** | MOD-10 con .mdb pequeño real (H0 POC), MOD-09 con fixtures XML SOAP cacheados (offline), MOD-08 con .mdb MASTER plantilla; MOD-15 con mail fake y FTP sandbox | Sí (excepto FTP real = manual sandbox) |
| **Feature** | Flujos Filament (carga, resultados, consolidar, informes con filtros, admin CRUD, portal: cargar/reenviar/observar), policies RN-001, MFA Flag env | Sí |
| **Paridad (regresión crítica)** | Excels reales 2024–2026 preprocesados + JSON de resultados esperados (capturados desde ValidadorRem con export) + comparación correctos/errores/control fila a fila (CA-M1-EXTRA, 50 archivos) + ParidadZip (mismo input GENERA con ambos motor es y tabla Datos/DatosC/Registros fila a fila — CA-014/H3) | Sí — suite dedicada o como `tests/Parity/` |
| **E2E manual** | Flujo completo establecimiento (CA-M7-EXTRA), paridad con portal Minsal de prueba (CA-M3-EXTRA), responsive portal en móvil, restore backup drill | Manual con checklist en TASK |
| **NFR/Medición** | Timing de validación (CA-NF-01 < 30 s archivo 2000 filas), generación zip (CA-NF-02 < 60 s), backup (CA-NF-03) | Sí como tests de rendimiento básicos en suite Nightly (rechazar regression) |

### 13.2 Estrategia de paridad (crítica — valida OBJ-04/OBJ-05)

1. **Fixture harvesting (hito común en FASE-2):** un script/artisano helper local **fuera de la app** exporta desde ValidadorRem: (a) resultados de validación por archivo (correctos/errores/control → JSON), (b) generado .mdb → tablas Datos/DatosC/Registros → CSV/JSON. Con los excels reales originales, se versionan fixtures bajo `tests/Parity/fixtures` (sin datos sensibles fuera del servicio — solo entrega para pruebas internas).
2. **Motor de comparación tolerante:** clase `ParidadService` compara colecciones fila a fila como clave (estab-mes-año-hoja-línea-col) y reporta mismatches con encoding exacto (float según tipo, null vs 0, etc.). Fallas de tipo aceptables tratadas explícitamente (PD-PLAN-05).
3. **Ciclo:** cada cambio de motor → correr paridad → corregir hasta 100% verde. Se mapea directamente a CA-M1-EXTRA/CA-014.

### 13.3 Cobertura de criterios de aceptación

- CA-001..CA-005 → Feature Carga interno.
- CA-006..CA-013 + CA-M2-EXTRA → Feature Informes + Export.
- CA-014 + CA-M3-EXTRA → ParityZip integraiva + E2E manual.
- CA-015/CA-016 → Integration con fixtures + conteos.
- CA-017..CA-023 + CA-M5-EXTRA → Feature admin + tema.
- CA-024/M6 → Integration migración + reejecutor idempotente.
- CA-025..CA-032 + CA-M7-EXTRA → Feature portal de flujos (Livewire) + E2E manual.
- CA-033 → Unit contenidos + mail/FTP fake + job programado en test calendar.
- CA-SEG-01..06 → Feature security + config Apache checks en runbook.
- CA-NF-01..04 → Nightly timing + Lighthouse/MV audit responsive.

---

## 14. Secuencia de implementación (dependencias ordenadas)

### 14.1 Grafo de dependencias (Fase 4 del metodología)

```
FASE-0 (H0 Contratos técnicos)
  ↓ condición: POCs green (COM/ACE leer+escribir .mdb; SoapClient WSDL; extensiones; PhpSpreadsheet xlsx/xls/xlsm; cola worker Windows)
FASE-1 Zócalo (app, paneles,Shield, tema, seeders, usuarios admin, modelo DAT migrations, AislamientoScope, activitylog, siembra catálogos vía MOD-10 mdb o backup mdb)
  ↓ condición: migrate:fresh --seed OK; login ambos paneles; Shield roles OK
FASE-2 Motor validación + cola (MOD-02, MOD-03, MOD-05, Paridad harness, tests RN)
  ↓ condición: Paridad carga verde sobre ≥10 fixtures representativos (CA-M1 progresivo)
FASE-3 M1+M2 básico interno (carga individual/masiva, resultados 3 tabs, consolidar, matriz, control, errores, export)
  ↓ condición: CA-001..CA-009, CA-013 verified
FASAT-4 y FASE-5 (independientes entre sí tras FASE-3, pueden paralelizarse):

FASE-4 Minsal M4+M3: MOD-09 sync+histórico; MOD-08 generación zip + ParityZip   ← depende de FASE-3 (datos reales consista)
FASE-5 Migración M6: MOD-13 comando, verificación conteos, siembra estab_series ← depende de FASE-1 modelo + MOD-10 (y útil antes que FASE-6 para RF-020 siembra)
  ↓ ambos: FASE-4 branch → CA-014/15/16 verde; FASE-5 → CA-024/M6 verde
FASE-6 Administración M5 completa (MOD-11, MOD-12 feriados/ventanas, MOD-18 tema, MOD-17 backups, auditoría view)
  ↓ condición: CA-017..CA-023; feriados 2025-2027 sembrados; back up runbook OK
FASE-7 Portal M7 (MOD-14 login/calendario/carga/versionado/observaciones/historial/cumplimiento)  ← depende FASE-6 (ventanas activas) y FASE-2 motor
  ↓ condición: CA-025..CA-032, CA-M7-EXTRA E2E (más hardened vhost portal)
FASE-8 Notificaciones + Informes re/Final + Hardening (MOD-15 notificaciones (a)(b); completar informes RF-010..RF-012; dashboard cumplimiento final; WAF/rate limiting full; performance Nightly; checklist GO transición; capacitación 86 establecimientos; paralelo con ValidadorRem 2 ciclos hasta corte)
```

**Resolución de dependencia circular detectada:** el motor (FASE-2) requiere catálogos sincronizados (RF-015) y Minsal podría dependere de datos (FASE-4). Se rompe así: **FASE-1 incluye siembra inicial de catálogos desde copias Global.mdb locales vía MOD-10 CatalogMdbSource** (misma tecnología, sin dependencia de que Minsal esté online). El paridad también sirve si catálogo Online cambia (esto ya cumpliría RF-015 con confirmación manual posterior).

### 14.2 Detalle de fases

| Fase | Objetivo | Componentes | Requisitos | Entregable verificable | Condición de cierre |
|---|---|---|---|---|---|
| **FASE-0** H0 | Contratos técnicos de riesgo | POCs: com_dotnet ACE reader/writer en .mdb; SoapClient WSDL Minsal; PhpSpreadsheet las 3 extensiones; queue worker Windows; Task Scheduler | LIM-019, RNF-019, PEND-001 | Documento H0: resultados POCs + versiones fijadas composer.lock + decisiones PD-PLAN-05/PD-PLAN-06 con terugketen | Go/No-Go: todos los POCs pass; si no, bloquear y plan B |
| **FASE-1** Zócalo | Base app operativa | Laravel+Filament 2 paneles, Shield, MFA setup, theme Osorno base, migrations DAT-*, seeders (servicio 23, admin, feriados 2025-2027, series), AislamientoScope, activitylog, CatalogMdbSource seed | DAT-01..22, RF-021, RN-001, RN-014 parcial | Login ambos paneles, admin rols visible, modelos up, audit básica | Composer/Migrations/Seeders verdes; Shield policies básicas verificadas |
| **FASE-2** Motor | Núcleo de negocio más crítico | MOD-02/03/05, arnés de paridad + ≥10 fixtures, subvalidadores, EstadoCarga | RF-003, RN-005..RN-020 | Motor corre en CLI/job: 10 fixtures de paridad verdes + tablas de casos unitarios | Paridad verde; los jobs procesan sin bloquear la UI |
| **FASE-3** M1+M2 básico | Ciclo interno completo | MOD-06 (individual/masiva/resultado/consolidar), MOD-07 (matriz, control, errores, export) | RF-001, RF-002, RF-004..RF-009, RF-013 | Panel interno operativo para carga de este mes (datos reales), matriz iguala ValidadorRem | CA-001..CA-009 + CA-013; pilot interno UAT equipo REM |
| **FASE-4** Minsal M4+M3 | Sincronías y salida Minsal | MOD-09 (sync/histórico), MOD-08 (GeneradorZip), ParityZip | RF-014..RF-016, RN-025..RN-038 | (a) catálogos refrescados con Normalización ok; (b) .zip con paridad fila a fila y subido a portal Minsal prueba | CA-014..CA-016; Hito H3 formalmente cerrado (zip aceptado CA-M3-EXTRA) |
| **FASE-5** Migración M6 | Histórico 2024-2026 | MOD-13 comando completo | RF-024, RN-047..RN-049 | Ejecución `rem:migrar` idempotente con conteos conciliados en reporte | CA-024 + CA-M6-EXTRA; establecimiento_series sembrado (si PEND-008 resuelto) |
| **FASE-6** Admin M5 | Gestión completa | MOD-11 (usuarios/cuentas/ventanas/series/servicio/tema/auditoría), MOD-12 (feriados+calendario), MOD-17 (backups), MOD-18 (tema Osorno) | RF-017..RF-023, RN-002, RN-039..RN-046 | Admin puede: crear cuentas 86 estab, abrir ventana marzo 2026, ver calendario, backups automáticos en trayecto, tema con colores invertido dark | CA-017..CA-023 + CA-M5-EXTRA |
| **FASE-7** Portal M7 | Recepción trazable | MOD-14 completo, policies reforzadas, rate limiting vhost | RF-025..RF-032, RN-001, RN-009..RN-016, RN-050..RN-062 | 86 establecimientos (o muestra de 10) suben series reales; flujo aceptación→consolidación→2da subida funciona | CA-025..CA-032; E2E CA-M7-EXTRA pass; CA-SEG-01/02/03 pass |
| **FASE-8** Notif + cierre | Automatización y hardening | MOD-15 (recordatorio FTP día 3 + post-carga), resolver RF-010/RF-011/RF-012, dashboard cumplimiento, performance, RNF-014 WCAG, checklist transición | RF-010..RF-013, RF-033, RN-018, RNF-* | Recordatorios y confirmaciones reales en UAT; 2 ciclos paralelo a ValidadorRem preparada | CA-033, CA-NF-01..04; GO transición con checklist firmado |

### 14.3 Orden y dependencias (resumen Fase 4 de la instrucción)

| Dependencia | Quién necesita | Por qué | Condición de satisfecha | Trabajo independiente posible |
|---|---|---|---|---|
| FASE-2 → FASE-1 | Motor usa catálogos, modelos, cola | RN-015..RN-017 requieren cat_*, jobs requieren COLA | seed de catálogos en FASE-1 | Lector Excel/POC pueden construirse ya en FASE-0/1 |
| FASE-3 → FASE-2 | UI M1 muestra exactamente salidas del motor | Grillas deben reflejar motor (RF-004) | Motor paridad verde | Recursos de admin y UI mientras tanto |
| FASE-4 → FASE-3 | Generador zip necesita datos cargas reales | RF-014 no tiene nada si no hay datos | cargas guardadas (RF-005) | FASE-5 puede correr en paralelo |
| FASE-5 → FASE-1 | Migración necesita modelo y lector mdb | migrar escribe DAT-13..16 lee mdb | lector mdb (FASE-0 H0/FASE-1) | Puede paralelizar con FASE-4 |
| FASE-6 → FASE-1 | Admin usa Shield/tema/modelos | Base común | zócalo listo | موضوع |
| FASE-7 → FASE-6 | Portal necesita ventanas activas y cuentas creadas | RN-044/RF-027 requieren ventana | admin verificada | POCs de portal login antes sin ventanas |
| FASE-7 → FASE-2 | Portal valida con el mismo motor | RN-054 exige las mismas reglas | motor de paridad verde | — |
| FASE-8 → FASE-6 y FASE-7 | Notificaciones dependen de ventanas/cuentas/portal | Días 3 hábil y confirmación | FASE-6, FASE-7 | Informes restantes independientes |

**Rutas paralelas seguras:** FASE-4 ∥ FASE-5 (después de FASE-3). FASE-6 UI puede iniciar detrás de FASE-3 (recursos sin ventanas no dependen del motor).

---

## 15. Riesgos y decisiones pendientes

### 15.1 Riesgos

| ID | Riesgo | Probabilidad/Impacto | Consecuencia | Mitigación |
|---|---|---|---|---|
| RIES-01 | COM/ACE OLEDB 12.0 no compatible con .mdb Minsal actual o falla en server (LIM-019) | media / crítico | RF-014 bloqueado; sin salida Minsal | POC obligatoria en FASE-0 antes de continuar; Plan B documentado: mantener generación por ValidadorRem durante transición mientras se investiga (no bloquea resto del sistema); seguimiento PEND-010 |
| RIES-02 | Paridad de reglas no 100% (érfalg casuística RN-018 excepciones, hojas de control) | alta / alto | CA-M1-EXTRA/CA-014 fallan; confianza en isError | Arranque de paridad temprano (FASE-2), fixtures reales amplios, revisión conjunta con equipo REM por serie; rollout por serie no hasta verde |
| RIES-03 | Performance de PhpSpreadsheet en carpeta masiva 440 archivos/mes (RNF-004) | media / medio | Cola lenta, frustración equipo | ReadFilter por rangos Thojas, batch size ajustable, profiling FASE-3, worker prioridad (portal primero en ventana) |
| RIES-04 | Worker de colas en Windows se detiene silenciosamente (Task Scheduler + queue:work) | media / alto | Trabajos no procesados y ruptura de SLA (RNF-003) | Health-check en dashboard (último mensaje procesado), restart automático con trigger de Task Scheduler, log de worker, alerta admin |
| RIES-05 | Servicios Minsal GET/SOAP caen o cambian (LIM-017) | media / medio | Sync sin actualizar → catálogos stale → rechazos | Cascada 3 niveles + caché del último bien conocido + alertar admin para importación manual |
| RIES-06 | Consultas informes lentas sobre `datos` 2.3M filas | media / medio | UI lenta, incumplimiento de tiempos | Índices compuestos (§5.1), dimensionar consultas por año, evitar count(*) sin límite en widgets (consultas agregadas); no particionado (FUERA-004) |
| RIES-07 | Adopción portal por 86 establecimientos (usuarios novatos) | media / medio | Envíos por correo continúan informalmente, ventana incumplida | Capacitación hipótesis (video + manual + canal móvil), FASE-7 muestra con 10 establecimientos pilotos antes de full, doble canal en transición |
| RIES-08 | XAMPP expuesto a internet (RIESGO de superficie) | ALTA / medio-alto | ataque superficie portal | Apache hardening, vhost mínimo, rate limiting, WAF si disponible (PD-PLAN-04), actualizaciones parches vigilante, VPN sugerido si infraestructura lo permite |
| RIES-09 | API feriados inestable/cambia | baja / bajo | Ventanas mal calculadas | Precarga manual editable (RN-009) es fuente de verdad; API solo sincroniza anual + test calendario de suite |
| RIES-10 | FTP credenciales/estructura no disponibles (PEND-007) | media / bajo | Recordatorios de la FASE-8 no operativos (queda vía SMTP de respaldo) | Router con canales configurables; FTP sandbox test manual; decisión PD-PLAN-03 urgente antes FASE-8 |
| RIES-11 | Compatibilidad Filament 4 + Shield/TOTP plugins | media / medio | FASE-1 bloqueado o trabajo extra | Fix versions composer.lock, POC inversión en FASE-0/H0; alternativa: Shield custom policies manual (esfuerzo conocido), sin plugins de terceros no soportados |
| RIES-12 | Migración 2.3M filas lenta o falla a medias | media / medio | histórico incompleto para transición | Transacción por serie/año + reanudación idempotente + report LIM-024; store pre-file timing POC en FASE-0/H0 si accessible |

### 15.2 Decisiones pendientes (tracking)

Listado completo en §2.3 (PD-PLAN-01..07), sobre las pendientes de SPECS (PEND-001..005 resuelta en SPECS; PEND-006..010 siguen abiertas y se mapean a PD-PLAN-02/03/06/07/05 respectivamente). Ninguna decide ya aquí; todas documentadas con impacto.

---

## 16. Auditoría de trazabilidad

### 16.1 Cobertura requisito → solución técnica

| Requisito / Regla | Solución prevista | Módulo | Fase | CA asociado |
|---|---|---|---|---|
| RF-001..RF-005 (M1) | MOD-06 con MOD-02/03/04/05 | MOD-06 | FASE-2/3 | CA-001..CA-005 |
| RF-006..RF-013 (M2) | MOD-07 | MOD-07 | FASE-3 + FASE-8 (resto) | CA-006..CA-013 |
| RF-014 (M3) | MOD-08 vía MOD-10 | MOD-08 | FASE-4 | CA-014 |
| RF-015/RF-016 (M4) | MOD-09 | MOD-09 | FASE-4 | CA-015/CA-016 |
| RF-017..RF-023 (M5) | MOD-11 (+ MOD-12/16/17/18) | MOD-11 | FASE-6 | CA-017..CA-023 |
| RF-024 (M6) | MOD-13 | MOD-13 | FASE-5 | CA-024 |
| RF-025..RF-033 (M7) | MOD-14 (+ MOD-15/12/16) | MOD-14 | FASE-7 + FASE-8 (notif) | CA-025..CA-033 |
| RN-001..RN-002 (roles/cuentas) | MOD-16 policies + MOD-11 | MOD-16 | FASE-1/6 | CA-SEG |
| RN-003..RN-020 (motor) | MOD-02 con catálogos | MOD-02 | FASE-2 | CA-003/CA-M1-EXTRA |
| RN-021/RN-022 (consolidación) | MOD-01 consolidador | MOD-01/MOD-06 | FASE-3 | CA-005 |
| RN-023/RN-024 (estados/matriz) | Modelos estados + MOD-07 widget | MOD-07 | FASE-3 | CA-006 |
| RN-025..RN-028 (Minsal) | MOD-08 | MOD-08 | FASE-4 | CA-014 |
| RN-029..RN-038 (sync) | MOD-09 | MOD-09 | FASE-4 | CA-015/CA-016 |
| RN-039..RN-049 | MOD-11/MOD-13 | MOD-11/13 | FASE-5/6 | CA-018..CA-024 |
| RN-050..RN-065 (portal) | MOD-14/15 + MOD-12 | MOD-14 | FASE-7/8 | CA-025..CA-033 |
| RNF-001..RNF-020 | Distribuidos: infra (17/19 FASE-0/1), perf (3/4/5/6/7 por fases), seguridad (12/15/16/17 FASE-8), feriados (20 FASE-6), paridad (18 FASE-2/4) | — | todas | CA-NF-01..04 |
| LIM-001..LIM-024 | Catálogo de errores + comportamientos por módulo (§11) | varios | continuo | cubiertos en tests por módulo |
| FUERA-001..FUERA-005 | Excluidos explícitamente del plan; no se introduce tecnología para ellos | — | — | — |

### 16.2 Checklist de auditoría (resultados)

- ✅ Cada requisito relevante tiene solución técnica prevista (ningún RF sin cobertura).
- ✅ Decisiones técnicas respetan AGENT.md §8 (todas TEC-01..TEC-14 derivan de la tabla AGENT, sin adiciones).
- ✅ Arquitectura satisface SPECS (2 paneles, motor parado por datos, colas, IDs `idservicio`, roles 4, notificaciones, migración, seguridad).
- ✅ Sin tecnologías o componentes sin justificación (§2 incluye Justificación y Restriction en cada fila).
- ✅ Riesgos principales documentados (12 riesgos con mitigaciones; incluyendo peligro de paridad y COM).
- ✅ Fases con entregables verificables (main 9 fases con condición de cierre y CA asociadas).
- ✅ Dependencias determinan orden válido (grafo §14.1; única circular catálogos ↔ Minsal resuelta con siembra inicial desde .mdb local en FASE-1, documentada explícitamente).
- ✅ Estrategia de pruebas permite verificar CA (§13.3 mapeo completo).
- ✅ Decisiones pendientes no presentadas como confirmadas (PD-PLAN-01..07 con estado y origen; PEND resueltas en SPECS no reabiertas).
- ⚠️ Nota: PD-PLAN-05 (tipos DECIMAL de col01..col50) podría derivar en re-migración de datos si cambia en FASE-4 — validar en FASE-0 (POC COM con introspección de tipos del .mdb real) **antes** de congelar las migraciones de FASE-1, para evitar retrabajo.
- No se detectaron contradicciones bloqueantes con AGENT.md ni con SPECS.md.

---

## Confirmación

Documento guardado en: `C:\xampp\htdocs\www\CargasREM\specs\PLAN.md`

Este plan responde a: **cómo se construirá el MVP, qué componentes se necesitan, qué dependencias existen y en qué orden implementarlos** (FASE-0 a FASE-8, grafo §14.1). El siguiente paso natural (no ejecutado automáticamente) es la generación del inventario de tareas atómicas en `specs/TASK.md`.
