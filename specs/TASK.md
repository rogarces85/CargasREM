---
title: WebCargaREM — TASK.md
version: 1.0
date: 2026-10-26
sources: specs/AGENT.md + specs/SPECS.md + specs/PLAN.md
---

Estado de implementación verificado por inspección del repositorio (2026-10-26):
- **No existe proyecto Laravel todavía** (no hay `composer.json`, `app/`, migraciones ni código).
- El repositorio contiene: `specs/` (AGENT, SPECS, PLAN), `INFORME.md`, `WebCargaREM.md`, y el directorio `BD/` con los archivos reales de datos (Global.mdb, MASTER.mdb, MasterDB/MasterC.mdb, DatosSERIE*/ControlSERIE* 2012-2026, Trans.mdb y bases Temporal* de trabajo) — **activos reutilizables** para H0, paridad y migración.
- ⚠️ **`BD/` es SOLO local**: contenido sensible + >700 MB. Ignorada por git (`/BD/` en `.gitignore`); nunca commitear ni subir a GitHub. Inventario, roles y reglas de manejo en `docs/BD-LOCAL.md`.
- Consecuencia: **0 tareas completadas; todas listadas quedan pendientes.** Ningún check se marca sin evidencia ejecutable.

## Convenciones

- `T-xxx` tarea atómica; `Depende de:` referencias T-*; `Refs:` identificadores de SPECS (RF/RN/CA/RNF/LIM/PEND/OBJ/FLUJO) y de PLAN (MOD/TEC/DAT/FASE/RIES/PD-PLAN).
- Estados: `[ ]` pendiente · `[x]` completada con verificación · `[⚠]` (marca textual) parcialmente bloqueada por decisión externa.
- Verificación mínima por defecto: la condición "Hecho cuando" es comprobable manualmente o con test automatizado cuando se indica.
- La operación crítica más arriesgada se fija en H0; la cadena de paridad (CA-003 / CA-014) es la columna vertebral del MVP.

---

# FASE A — H0: contratos técnicos y provisionamiento (FASE-0 de PLAN)

**Objetivo:** eliminar todo riesgo tecnológico (extensiones, drivers, colas) antes de construir encima.
**Resultado esperado:** documento con POCs verdes o bloqueos documentados, proyecto Laravel funcional, `composer.lock` fijado.
**Condición de cierre de fase:** T-011 marcado con GO y sin bloqueos abiertos de PD-PLAN-05.

- [ ] **T-001** — Verificar y activar extensiones PHP en el XAMPP del servidor (`com_dotnet`, `soap`, `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `openssl`).
  Hace: habilitar en `php.ini` y confirmar por `php -m` sin conflictos con Apache.
  Refs: RNF-019, LIM-019, AGENT §8, PEND-001 (ya resuelta por usuario). Hecho cuando: `php -m` lista las extensiones y una página `phpinfo` en el vhost la confirma.
- [x] **T-002** — Provisionar ambiente de desarrollo local (PHP 8.3+, Composer, MySQL) separado del servidor.
  Hecho cuando: `php -v` ≥ 8.3 y MySQL accesible desde consola.
  **Evidencia (2026-10-26):** PHP 8.2.12 (XAMPP local) · Composer 2.9.7 · MySQL corriendo (puerto 3306 OK) · Apache 2.4 corriendo. ⚠️ *Desviación registrada:* PHP local es 8.2 (cumple mínimo de Laravel 12/Filament 4, el objetivo 8.3+ ideal queda anotado para la decisión H0 de T-011). Extensiones habilitadas en `php.ini` local: `intl`, `soap`, `com_dotnet` (DLLs confirmadas; backup `C:\xampp\php\php.ini.bak-20261026`). Debe reiniciarse el servicio Apache local para que el servidor web también las cargue.
- [x] **T-003** — Crear proyecto Laravel 12 + Filament 4 en la raíz del repo con **versiones fijadas en `composer.lock`**.
  Refs: TEC-01, TEC-02, RIES-11. Hecho cuando: `composer install --no-interaction` respeta lock; `php artisan serve` levanta.
  **Evidencia (2026-10-26):** Esqueleto instalado en la raíz del repo (`app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `tests/`, etc.). `composer.json` requiere `php ^8.2`, `laravel/framework ^12.0` (instalado **12.70.1**), `filament/filament 4.*` (instalado **4.16.0**, sin advisories de seguridad), livewire 3.8.11. `composer.lock` generado y fijado. `php artisan --version` OK; migraciones base corridas (base SQLite de scaffold; el cambio a MySQL queda para T-010/T-013 de FASE B). Smoke test: `php artisan serve` levanta y responde **HTTP 200**. Existente preservado: `.gitignore` propio, `docs/`, `specs/`, `INFORME.md`, `WebCargaREM.md`, `BD/` (ignorada por git).

- [x] **T-004** — POC lectura Excel: PhpSpreadsheet `setReadDataOnly(true)` sobre un `.xls`, un `.xlsx` y un `.xlsm` con ReadFilter por rangos.
  Refs: TEC-04, RN-003/RN-004, LIM-001. Hecho cuando: script imprime valores cacheados de celdas por coordenada para los 3 formatos y detecta archivo sin caché ("fórmulas no evaluadas").
  **Evidencia (2026-10-26):** POC `poc/lectura_excel.php` PASÓ con PhpSpreadsheet 5.10 — los 3 formatos (.xls/.xlsx/.xlsm) leídos con `setReadDataOnly(true)` + `IReadFilter` (A1:H20); valores cacheados recuperados vía `getOldCalculatedValue()` (30); LIM-001 detectado con la regla `getValue() starts with "=" AND getOldCalculatedValue() === null` para .xlsx/.xlsm. Hallazgo registrado en docs/H0.md: BIFF8 (.xls) no puede almacenar fórmula sin resultado (writer fuerza 0.0) — detección estricta limitada a .xlsx/.xlsm, riesgo residual bajo porque los .xls del flujo REM provienen de Excel.
- [x] **T-005** — POC escritura COM/ACE: copiar `BD/MASTER.mdb` a temporal, insertar 2 filas en tabla `Datos` vía `com_dotnet`, confirmar fila `Registros`.
  Refs: TEC-06, RN-026, LIM-019, RIES-01. Hecho cuando: los valores leídos de vuelta coinciden exactamente (incluye tipo y nulos).
  **Evidencia (2026-10-26):** POC `poc/com_escribe.php` PASÓ con `Microsoft.ACE.OLEDB.12.0` 64-bit (ADODB + com_dotnet): copia de la plantilla a `storage/rem/poc/MASTER_poc.mdb` (original intocado — hash SHA-256 pre/post idéntico `f9733be2…`); INSERT parametrizado de 2 filas en `Datos` (56 col.) y SELECT de vuelta con **match exacto** en texto acentuado/ñ, decimales Double (1000.125), Ano/Mes Long. Registros: esquema `ano|CantReg|Fecha|idservicio|Mes|Serie` confirmado. Hallazgos en docs/H0.md: adDate exige `VARIANT(VT_DATE)`; tamaño de parámetro ≤ campo (VarChar); IdServicio VarChar(2) en plantilla vs Long en base serie (casteo necesario); DEC-POC-01: ColNN siempre float nativo.
- [x] **T-006** — POC lectura COM/ACE de `.mdb` real (usar `BD/DatosSERIEBM2025.mdb`) con introspección de tipos de `col01..col50` y `Mes`.
  Refs: TEC-06, PD-PLAN-05, PEND-010. Hecho cuando: documento con tipos/escala decisivos para las migraciones (p. ej. DECIMAL(p,2)) y resolución de conversión `"," → "."`.
  **Evidencia (2026-10-26):** POC `poc/com_lee_tipos.php` PASÓ — base real (6,4 MB) abierta en modo solo lectura (`adModeRead`, hash pre/post idéntico `ab407732…`, original intocado). `OpenSchema(4)`: 56 columnas idénticas a la plantilla (Ano Long · CodigoPrestacion VarChar(50) · Col01..Col50 **Double prec 15** nullable · fechaIngreso adDate · IdEstablecimiento VarChar(7) · Mes Long · IdServicio **Long**). Muestra fila 1 de tipos PHP de llegada (double/NULL/integer). JSON en `storage/rem/poc/datosseriebm2025_columnas.json` como insumo PD-PLAN-05; propuesta de tipos MySQL (datos: Decimal(15,4)) en docs/H0.md. Normalización coma→punto (RN-037): DEC-POC-01 fija que el mapeador normaliza antes de pasar el float al parámetro ACE.
- [x] **T-007** — POC `SoapClient` contra el WSDL Minsal (timeout y `exceptions=1`).
  Refs: TEC-08, LIM-017. Hecho cuando: una operación catalog responde y se registra XML crudo de muestra.
  **Evidencia (2026-10-26):** POC `poc/soap_minsal.php` PASÓ — SoapClient con `trace=1`, `exceptions=1`, `connection_timeout=10`, `timeout=30`, `cache_wsdl=WSDL_CACHE_NONE`, GZIP; WSDL real en 0.76 s; operaciones `Version` (168 KB, 0.69 s) y `servicio` (7.9 KB, 0.1 s) respondieron como DataSet ASMX (stdClass). XML crudo request+response archivado en `poc/fixtures/minsal/` (webservicedb.wsdl.xml, servicio.xml, version.xml). Hallazgo: filas llegan como `<{tabla} diffgr:id="…" msdata:rowOrder>` y métodos case-sensitive (`Thoja`, `Version`), registrados en docs/H0.md.
- [x] **T-008** — POC Guzzle GET simple + SimpleXML sobre endpoint `/datos` de `webservicedb.asmx`.
  Refs: TEC-08, RN-029. Hecho cuando: XML parseado a array >0 registros y se archiva muestra como fixture offline.
  **Evidencia (2026-10-26):** POC `poc/get_xml_minsal.php` PASÓ con Guzzle 7.16 (timeout 30 s / connect 10 s) + SimpleXML (XPath `//*[@msdata:rowOrder]` con `registerXPathNamespace`): GET `/servicio` → **30 servicios**, `/Version` → **939 versiones** (mapa SerieExcel↔Serie), `/datos` → **3.205 establecimientos** (2.1 MB). Parseados a array PHP con registros y campos esperados. Fixtures: `servicio_get.xml` (completo), `version_get.xml` (completo), `datos_get_recorte.xml` (20 filas). Hallazgo: bindings GET requieren el nombre exacto y case-sensitive del método ASMX (fallo con nombre incorrecto → HTTP 500), quedando la vía GET+XML como primaria confirmada (WebCargaREM §229) y SOAP como respaldo — registrado en docs/H0.md.
- [x] **T-009** — POC cola: migrar `jobs` table, driver `database`, y job dummy ejecutado por worker Windows.
  Refs: TEC-05, TEC-18 (TEC-18: schedule:run Task Scheduler), RIES-04. Hecho cuando: job dummy se procesa y Task Scheduler reinicia worker con SkipIfRunning probado manualmente.
  **Evidencia (2026-10-26):** PASÓ con SQLite + `QUEUE_CONNECTION=database` y tabla `jobs` existente. Job `App\Jobs\PocDummyQueueJob` (marcador en `storage/rem/poc/queue_marker.txt`): dispatch(42) → 1 pendiente; worker oculto iniciado por `scripts/rem_worker_colas.bat` (BAT → PowerShell `Start-Process php artisan queue:work database … -WindowStyle Hidden`, lock PID en `storage/rem/worker.pid`) → marcador `19:32:48 | payload=42 | host=LENOVO6832`, cola a 0. **SkipIfRunning**: re-ejecución con worker vivo → `SKIP: worker activo PID 21240` (log `storage/logs/worker_poc.log`). **Task Scheduler**: tarea `WebCargaREM_worker_poc` (/SC MINUTE) creada y disparada con `schtasks /Run` → SKIP con worker vivo; tras `taskkill /PID 21240 /F` + `schtasks /Run` → **reinicio verificado** (nuevo PID 22092; marcador `19:33:33 payload=99`, cola 0). La tarea quedó `/DISABLE` en la máquina dev. Script versionado en `scripts/rem_worker_colas.bat`; hallazgos en `docs/H0.md`.
- [x] **T-010** — Crear `config/rem.php` (idservicio, timeouts Minsal, canales smtp/ftp, límites archivo), `.env` de demo con claves/secretos enumerados y `lang/es/rem.php` base (catálogo de mensajes LIM).
  Refs: TEC-01, RNF-016, §11 PLAN. Hecho cuando: `config('rem.idservicio')` devuelve 23 y los mensajes LIM existen en `lang`.
  **Evidencia (2026-10-26):** `config/rem.php` creado (§11 PLAN): `idservicio='23'`, servicio_nombre; minsal (base_uri, timeout 30/connect 10, SOAP respaldo, 7 operaciones case-sensitive, endpoints histórico); colas (driver database, worker_script, tarea programada); excel (max 20 MB, xls/xlsx/xlsm, rango lecturas); generador (plantilla MASTER, gzip 9); smtp/ftp (PEND-007 placeholder); ventanas (PEND-009 provisionable); backups; rutas storage. `lang/es/rem.php` con los **24 mensajes LIM** (`__('rem.lim.LIM-###')`). `.env.example` con bloques REM_* enumerados (sin secretos reales); locale es en `.env`/`.env.example`. Verificación CLI: `config('rem.idservicio')==='23'`, `trans('rem.lim.LIM-005')='Versión del formato no corresponde al mes/año/serie.'`, `count(trans('rem.lim'))=24`, `config('rem.excel.max_upload_mb')=20`, `config('rem.minsal.timeout')=30`.
- [ ] **T-011** — Redactar `docs/H0.md` con resultados de todos los POCs + decisión GO/NO-GO + resolución provisional de PD-PLAN-05 (se toma como base salvo contraindicación).
  Refs: PD-PLAN-05, PEND-010, RIES-01, T-001..T-009. Hecho cuando: documento archivado con salida cruda evidenciada y decisión firmada por parte técnica.

# FASE B — Zócalo: modelo, paneles, seguridad base (FASE-1 de PLAN)

**Objetivo:** infraestructura de datos y acceso operativa sin funcionalidad de negocio.
**Resultado esperado:** migrations + modelos + 2 paneles Filament login-ables + roles Shield + tema base + catálogos sembrados.
**Condición de cierre de fase:** T-029 verificado (migrate:fresh --seed + login exitoso en ambos paneles).

- [ ] **T-012** — Migraciones de tablas catálogo: `series`, `cat_diccionario`, `cat_thojas`, `cat_excep`, `cat_colexcep`, `cat_versiones`, `cat_software` con índices y unique por negocio.
  Refs: DAT-04, DAT-17..22 (PLAN §5.1); PD-PLAN-05 (tipos). Depende de: T-006, T-003. Hecho cuando: `php artisan migrate` corre sin errores y las claves/índices existen en MySQL (SHOW INDEX).
- [ ] **T-013** — Migraciones de tablas operativas: `servicios`, `establecimientos`, `users`, `establecimiento_series`, `cargas`, `datos`, `datos_control`, `hist_carga`, `log_errores` con FKs e índices compuestos (ver §5.1 PLAN).
  Refs: DAT-01,02,03,05,08,13..16. Depende de: T-006, T-003, T-012. Hecho cuando: migrate corre y constraints muestran falla esperada al insertar violación (prueba manual o test) — índices compuestos creados.
- [ ] **T-014** — Migraciones de recepcion/ventanas: `feriados`, `ventanas_carga`, `recepciones` (UNIQUE por grupo), `recepcion_versiones`, `recepcion_observaciones`, `recepcion_eventos`.
  Refs: DAT-06,07,09,10,11,12, TEC-17. Depende de: T-013. Hecho cuando: UNIQUE de recepciones rechaza duplicado por (estab, serie, mes, año).
- [ ] **T-015** — Migraciones de soporte: `generaciones_minsal`, `sincronizaciones`, `notificaciones_enviadas` + publicación/spatie activitylog.
  Refs: DAT-24..26, TEC-11. Depende de: T-013. Hecho cuando: tablas existen y log estructura acepta causer/IP/props.
- [ ] **T-016** — Migraciones de staging por carga: `datos_carga` y `datos_control_carga` (resultados transitorios asociados a `cargas`, purgadas al consolidar).
  Refs: derivación de RN-021/RN-022 (transacción DELETE+INSERT en consolidación, PLAN §10); hace consistentes RF-003 (población en job) y RF-005 (insert definitivo). Depende de: T-013. Hecho cuando: tablas existen y FK a `cargas`.
- [ ] **T-017** — Modelos Eloquent con relaciones, `$casts` de enums, tabla `Series` helpers y ScopeServicioActivo (idservicio configurable).
  Refs: MOD-01, RF-021, TEC-20. Depende de: T-012..T-015. Hecho cuando: tests unit models (relaciones, casts, scope) en green.
- [ ] **T-018** — Implementar `AislamientoScope` global (RN-001) y policies de modelos base (establecimientos/recepciones/cargas desde portal).
  Refs: RN-001, MOD-16, CA-SEG-01. Depende de: T-017. Hecho cuando: test unit demuestra que un user de establecimiento A no puede resolver modelo de B (0 filas).
- [ ] **T-019** — Instalar y configurar Filament Shield: generar permisos para los 4 ROL-* y recursos futuros (modo modular).
  Refs: TEC-10, ROL-* SPECS §3. Depende de: T-020. Hecho cuando: 4 roles creados y permisos recorben gestión en panel.

- [ ] **T-020** — Registrar `BackofficePanel` (rutas `/backoffice/*`, navegación 5 grupos RN-023, dashboard placeholder, tema base).
  Refs: MOD-06 (en panel), RN-023, SPECS §7.1. Depende de: T-003, T-017. Hecho cuando: login admin en panel y menú de grupos visible.
- [ ] **T-021** — Registrar `PortalPanel` (rutas `/portal/*`, middleware auth, sidebar Calendario/Cargar/Historial/Cumplimiento placeholder).
  Refs: MOD-14, RN-016, SPECS §7.2. Depende de: T-003, T-017. Hecho cuando: login de cuenta establecimiento tipo en panel portal.
- [ ] **T-022 ⚠** — Auth pieza MFA: interfaz `TOTPProvider` + middleware `EnforceMfa` que en producción fuerza admin/supervisor.
  Refs: TEC-15, RN-014, CA-SEG-05, LIM-022. Depende de: T-020. Bloqueo parcial: PD-PLAN-01 (librería TOTP concreta) — el middleware y la interfaz son implementables ya; el proveedor concreto se resuelve antes de FASE-7. Hecho cuando: tests fallan (o pasan) según `mfa_habilitado` y entorno.
- [ ] **T-023** — Seeder servicio (id=23, Osorno) + seteo de servicio activo en BD.
  Refs: DAT-01, RF-021, RN-010. Depende de: T-013. Hecho cuando: `config + BD` resuelven idservicio=23 y el scope lo aplica.
- [ ] **T-024** — Seeder usuario Admin inicial (email oficial, rol ADMIN) con instrucciones de password en `.env` y recovery de claves documentado.
  Refs: RF-017, RN-014, LIM-022. Depende de: T-023. Hecho cuando: login real del admin funciona desde consola en local.

- [ ] **T-025** — Seeder catálogos desde `BD/Global.mdb` vía lector COM (POC T-005): servicios, establecimientos, cat_* completos. Verificar conteos vs mdb.
  Refs: RN-031 (mismo mecanismo usado como fallback), MOD-09, RN-010, PD-PLAN-05. Depende de: T-005, T-012. Hecho cuando: conteos MySQL = conteos mdb por tabla (script exporta resumen) y establecimientos cargados (86 esperados, se reporta el conteo real).
- [ ] **T-026** — Seeder feriados 2025-2027 (nacionales + regionales de Los Lagos) precargados desde datos oficiales con columna editable.
  Refs: RN-009, RN-042, RN-050. Depende de: T-014. Hecho cuando: listado 2025-2027 poblado y editable vía admin (task de API en T-075 no bloquea esto).
- [ ] **T-027** — Tema Filament Osorno base (paleta institucional, logo, dark/light nativo activado en ambos paneles).
  Refs: MOD-18, RF-022, RIES-11. Depende de: T-020, T-021. Hecho cuando: dark/light switch cambia visualmente y no hay conflicto de clases.
- [ ] **T-028** — Suscripciones activitylog base en eventos claves (login, cambio estado carga, sync, acciones admin) con causer+IP.
  Refs: TEC-11, RNF-012, CA-SEG-06. Depende de: T-015, T-020. Hecho cuando: al menos 4 eventos generan registros con propiedades correctamente.
- [ ] **T-029** — Ejecutar `php artisan migrate:fresh --seed` completo + smoke test de login en ambos paneles + confirmar dashboard backoffice sin errores.
  Depende de: T-012..T-028. Hecho cuando: verificación manual con snapshot de salida y sin excepciones en logs.

# FASE C — Motor de validación (FASE-2 de PLAN)

**Objetivo:** núcleo de negocio con paridad comprobable contra ValidadorRem.
**Resultado esperado:** motor ejecutable vía job, suite de casos de regla y paridad iterativa hacia 100%.
**Condición de cierre de fase:** T-041 con paridad verde sobre fixtures disponibles + suite de reglas en verde.

- [ ] **T-030** — `ArchivoService`: guardar fuera de webroot (`storage/rem/archivos/{año}/{mes}/{estab}/{serie}/{version}`), hash SHA-256, listar y servir descarga autenticada vía ruta autorizada.
  Refs: TEC-13, MOD-04, RN-017, RNF-009. Depende de: T-017. Hecho cuando: test unit guarda/lee/borra (borrado solo admin) y hash coincide.
- [ ] **T-031** — `LectorExcelService`: detectar formato por año (RN-004), abrir con `setReadDataOnly`, ReadFilter por rangos de `cat_thojas`, acceso por coordenadas.
  Refs: TEC-04, MOD-03, RN-014. Depende de: T-004, T-025. Hecho cuando: script lee rango preciso de Thojas sobre un Excel real y arroja excepción controlada para sin caché con mensaje LIM-001.

- [ ] **T-032** — DTOs y enums de dominio: `ResultadoValidacion`, `ErrorCelda(hoja, línea, columna, valor, mensaje_id)`, `EstadoCargaEnum`, `EstadoVersionEnum`, orígenes.
  Depende de: T-017. Hecho cuando: DTOs tipados consigan serializar a BD y de vuelta (round-trip test).
- [ ] **T-033** — `ValidadorEncabezado` RN-009..RN-013 con tabla de casos: mapeo Establecimiento (C3:H3), Mes (C6+D6), Año (B7), Serie (B17 primeros 7-8), Versión (A9 primeros 12); validaciones contra `servicios`, `cat_versiones` y coherencia selección.
  Refs: RF-003, LIM-003..005. Depende de: T-032, T-031, T-025. Hecho cuando: suite unit con ≥15 casos encabezado (válido, servicio ajeno, mes desalcanzada, versión incorrecta) en verde.
- [ ] **T-034** — `ValidadorHojas` RN-014..RN-017: recorrer Thojas por serie/año, posicionar prestaciones con Diccionario (línea, inicio, fin), aplicar Excep/colexcep; LIM-006/007 (hoja no configurada, prestación no en Diccionario).
  Depende de: T-033, T-031, T-025. Hecho cuando: casos unit de hojas/rangos/excepciones en verde (≥20 casos).
- [ ] **T-035** — `ValidadorNumericos` RN-018 + RN-020: no numérico/negativo → error; >1 decimal → error corta fila; 1 decimal → error salvo hojas A09/A21/SERIE BS/SERIE D; entero válido; filas todo-0/null no insertadas; LIM-008..011.
  Depende de: T-034. Hecho cuando: tabla de casos con las 6 combinaciones (incluye A09/A21/BS/D con 1 decimal OK) en verde.
- [ ] **T-036** — `ValidadorControl` RN-019: hojas con nombre ≥6 chars → capturar `col01..col05` a staging de control.
  Depende de: T-034. Hecho cuando: caso con hoja "CONTROL1" produce registro de control correcto.
- [ ] **T-037** — `MotorValidacion` orquestador: llama encabezado → hojas → numéricos → control; produce `ResultadoValidacion` y estado final (`validada` | `con_errores` | falla crítica).
  Refs: RF-003, RN-005, MOD-02. Depende de: T-033..T-036. Hecho cuando: integración motor sobre un Excel sintético produce resumen con contador correctos/errores/control.
- [ ] **T-038** — `ValidarCargaJob`: worker job que ejecuta motor y persiste resultados en staging (`datos_carga`, `datos_control_carga`, `log_errores`) actualiza `cargas.estado` y dispara evento de actividad.
  Refs: RF-003, RN-003, TEC-05, RNF-003. Depende de: T-037, T-016, T-030, T-009. Hecho cuando: test feature encola job y muestra estado finalizado con datos en staging y log_errores poblados.

- [ ] **T-039** — Generador de fixtures sintéticos por regla (Excel sintético por cada RN-009..RN-020 y LIM-001..011) + suite unit con tablas de casos.
  Refs: RF-003, LIM-001..011 (casos motor), PD-PLAN-05. Depende de: T-038. Hecho cuando: `php artisan test --filter=Motor` corre >0 casos y cubre todas las reglas del motor.
- [ ] **T-040 ⚠** — `ParidadService`: marco de comparación colección-a-colección (clave estab/mes/año/serie/hoja/línea/col) con tolerancias explícitas de tipo/documentación.
  Refs: PLAN §13.2, CA-003, CA-M1-EXTRA. Hecho cuando: marco corre con 1 fixture y reporta mismatches descriptivos. Provisional: no depende de datos externos.
- [ ] **T-041 ⚠** — Suite paridad de carga: fixtures reales del equipo REM (Excel 2024-2026) + export de resultados de ValidadorRem → JSON; ejecución de comparación hasta 100%.
  Refs: CA-003, CA-M1-EXTRA, PEND-005 resuelta como "suite propia". Depende de: T-039, T-040. Bloqueo parcial: requiere provisión externa de Excel reales; suite corre con subset disponible (provisorio) y se re-valida con el set completo. Hecho cuando: suite genera reporte por fixture con estado; ≥10 fixtures verdes como cierre provisional y 100% del set para cierre final.
- [ ] **T-042** — Datos iniciales de paridad zip: exportar tablas `Datos`, `DatosC`, `Registros` desde `BD/DatosSERIEA2025.mdb` (referencia, solo lectura — ver docs/BD-LOCAL.md) a JSON/CSV versionado en `tests/Parity/fixtures/`, **depurado de datos sensibles** (que `BD/` nunca se versiona; solo los fixtures anonimizados/depurados viven en el repo).
  Refs: CA-014 (soporte de paridad zip), RIES-02. Depende de: T-006. Hecho cuando: fixtures de referencia versionados de forma reproducible (hash del mdb documentado).

# FASE D — Carga interna e informes básicos (FASE-3 de PLAN)

**Objetivo:** operación integrada del equipo REM para carga y consultas básicas.
**Resultado esperado:** ciclo carga→validación→consolidación operativo sobre datos reales del mes.
**Condición de cierre de fase:** T-055 verificado con CA-001..CA-009 + UAT del equipo REM sobre 1 mes real.

- [ ] **T-043** — UI carga individual: componente Filament con select serie/año (auto-mes), validación de extensión por año en vivo, archivo sea Upload, registre `cargas` estado `pendiente` y encola job.
  Refs: RF-001, CA-001, RN-003..005, LIM-002/LIM-020. Depende de: T-038, T-030, T-020. Hecho cuando: CA-001 se verifica manualmente + test feature.
- [ ] **T-044** — UI carga masiva: drag&drop carpeta o .zip, procesamiento en lote, filtro extensión/año, marcado `Ignorado` para los que no cumplen (RN-007), grilla con contador OK/Error/Ignorado.
  Refs: RF-002, CA-002, RN-006..008. Depende de: T-043. Hecho cuando: CA-002 (10 archivos → 8 encuelo y 2 ignorados) pasa en test feature.
- [ ] **T-045** — Página resultados de carga: 3 tabs (Correctos, log_errores filtrado, Control) con datos del staging/persistencia, export rápido, badge de estado final.
  Refs: RF-004, CA-004, RN-005. Depende de: T-043, T-038. Hecho cuando: los 3 tabs muestran los mismos valores que los datos fuente (verificación manual).

- [ ] **T-046** — `ConsolidadorService`: transacción única delete+insert (reemplazo LIM-012 opcional vía confirmación UI), `lockForUpdate` sobre (estab,mes,año,serie), upsert `hist_carga`, limpieza staging, actualiza estado `guardado`.
  Refs: RF-005, CA-005, RN-021/022, LIM-012. Depende de: T-038, T-045. Hecho cuando: test feature consolida una carga validada y re-ejecución con reemplazo produce mismos conteos sin duplicados.
- [ ] **T-047** — Dashboard backoffice: tarjetas resumen (archivos mes, pendientes, errores abiertos, fuera de plazo) + widget matriz cumplimiento con filtros y % por serie/mes.
  Refs: RF-006, CA-006, RN-023/024. Depende de: T-046 (datos reales), T-020. Hecho cuando: números cuadran manualmente con la BD para 1 mes.
- [ ] **T-048** — Informe Matriz Estab×Mes (EstabCargados) exportable Excel.
  Refs: RF-007, CA-007, RNF-016. Depende de: T-047. Hecho cuando: export produce .xlsx y contenido coincide con matriz en pantalla (mismo criterio de pivote).
- [ ] **T-049** — Informe Control faltante (cruce establecimientos × datos_control).
  Refs: RF-008, CA-008. Depende de: T-046. Hecho cuando: caso manual con 2 sin control detectado exactamente (test feature).
- [ ] **T-050** — Informe Log de errores (carga y recepciones) filtrado por serie/mes/establecimiento + export.
  Refs: RF-009, CA-009. Depende de: T-050. Hecho cuando: filtros funcionan y export coincide con columnas en pantalla.
- [ ] **T-051** — Exportación global: botón Export en cada informe o tabla con filtros aplicados.
  Refs: RF-013, CA-013, CA-M2-EXTRA. Depende de: T-047..T-050. Hecho cuando: .xlsx abre correctamente en Excel y LibreOffice con columnas/celdas intactas (recuento manual).

- [ ] **T-052** — RecepcionesResource (backoffice): listado con filtros (serie, mes, estab, estado, en_plazo, 2da subida) + detalle con versiones, validación, hilo de observaciones.
  Refs: MOD-14 lado interno, RF-031, SPECS §7.1. Depende de: T-020, T-014. Hecho cuando: vista accesible solo roles internos y filtros funcionan.
- [ ] **T-053** — Acciones de revisión: Aceptar / Observar (modal texto obligatorio) / Rechazar sobre `recepcion_versiones` con registro en `recepcion_eventos` + `recepcion_observaciones`.
  Refs: RF-031, RN-061/062, RN-013. Depende de: T-052. Hecho cuando: cada acción registra estado previo/nuevo y correcto en eventos (test feature).
- [ ] **T-054** — Consolidación de recepción aceptada: transacción al aceptar → mover staging a `datos`/`datos_control` con reemplazo de versión anterior (RN-012/013/057/060/011), actualizar vínculo `carga_id`.
  Refs: FLUJO-03/04, RN-013, CA-M7-EXTRA (integración). Depende de: T-053, T-046, T-038. Hecho cuando: tras aceptar vN, datos de esa versión quedan sin staging residudales al finalizar (consulta de staging limpia) y datos válidos en tablas definitivas.
- [ ] **T-055** — Suite feature M1+M2 básico: CA-001..CA-009 automatizados + inspección de estados (RN-023).
  Depende de: T-043..T-054. Hecho cuando: `php artisan test --filter=CargaInterna` con todos los CA en verde.

# FASE E — Integración y generación Minsal (FASE-4 de PLAN, paralelizable con FASE F)

**Objetivo:** CDP que sincroniza catálogos e históricos y produce la salida importable al Minsal.
**Resultado esperado:** sync transaccional auditado + `.zip` con paridad fila a fila (Hito H3).
**Condición de cierre de fase:** T-065 verde + T-066 práctica en portal Minsal de prueba.

- [ ] **T-056** — `ComMdbAdapter` service: wrapper único COM/ADODB (open/query/bulkInsert/close) con timeouts, reintentos de manipulación y mensaje de error LIM-019 explícito.
  Refs: MOD-10, TEC-06. Depende de: T-005, T-006. Hecho cuando: test de integración inserta/lee con datos real mdb pequeño y falla controlada cuando COM no disponible (simulado).

- [ ] **T-057** — `CatalogXmlSource`: cliente Guzzle+SimpleXML para 7 endpoints finance (datos, servicio, diccionario, Thoja, Excep, ColExcep, Version) con DTOs normalizados.
  Refs: RN-029. Depende de: T-008. Hecho cuando: cada endpoint parsea y produce colecciones con registros y campos esperados (fixtures offline).
- [ ] **T-058** — `CatalogSoapSource`: cliente SoapClient de respaldo con mapeos equivalentes + tests offline contra fixture WSDL/respuestas.
  Refs: RN-030, LIM-017. Depende de: T-057. Hecho cuando: misma salida comparable a la XML en fixture controlada y corrije mapeos.
- [ ] **T-059** — `CatalogMdbSource`: importador desde `Global.mdb` local (fallback último) + parámetro manual UI si sync automática falló.
  Refs: RN-031, LIM-017. Depende de: T-056, T-057. Hecho cuando: import seleccionable produce conteos iguales que T-025 (seed reference).
- [ ] **T-060** — `CatalogSyncService`: cascada Xml→Soap→Mdb, transacción por tabla (delete+reinsert), normalización ``,` → `.` y apóstrofes (RN-033), update `cat_software.version`, registro en `sincronizaciones`.
  Refs: RF-015, CA-015, RN-029..033. Depende de: T-057..T-059. Hecho cuando: sync en verde con BD local actualizado y `cat_software.version` = versión servidor (CA-015).
- [ ] **T-061** — Página Integración Minsal: botones sync (con progreso por tabla) y elegir fuente manual (mdb) + tabla de sincronizaciones históricas con resultado.
  Refs: FLUJO-05, MOD-09, LIM-017. Depende de: T-060. Hecho cuando: probar cascadea completa con los 3 escenarios (simulaciones de falla en XML).
- [ ] **T-062** — `HistoricoDownloaderService`: confirmación previa (RN-034), limpieza local (RN-036), reimersión normalizada (RN-037), reconstrucción `hist_carga` (RN-038), registro `sincronizaciones`.
  Refs: RF-016, CA-016, RN-034..038. Depende de: T-060. Hecho cuando: CA-016 completa en test con conteo coincidente (fixtures réseau).

- [ ] **T-063 ⚠** — `GeneradorZipMinsalService`: copiar MASTER.mdb, bulk insert `datos` (Col01..50) + `datos_control` (col01..05) + fila `Registros` (RN-026), comprimir GZip (RN-027), registrar `generaciones_minsal`, borrar intermedio.
  Refs: RF-014, RN-025..028, LIM-018/019. Depende de: T-056, T-046 (datos reales). Bloqueo parcial: PD-PLAN-06 (mes límite `Registros.Mes`) adopta usando el mismo criterio de ValidadorRem (consultado en T-006) hasta confirmación. Hecho cuando: zip se genera con registro de auditoría y hay LIM-018 cuando no hay datos.
- [ ] **T-064** — Página Minsal: selector año/serie + botón generar (job) + descarga + tabla historial generaciones con hash/tamaño.
  Refs: RF-014 UI, CA-014. Depende de: T-063. Hecho cuando: descarga operativa con permisos de rol y no otros roles.
- [ ] **T-065** — `ParityZipTest`: dado input de datos migrados del .mdb de referencia, generar zip y comparar tablas Datos/DatosC/Registros fila a fila contra fixtures de T-042.
  Refs: CA-014, Hito H3, RN-020. Depende de: T-063, T-042. Hecho cuando: comparación 100% verde para al menos la serie A (datos 2025) sobre la suite completa.
- [ ] **T-066** — Prueba E2E portal Minsal de prueba (manual, supervisada por equipo REM).
  Refs: CA-M3-EXTRA. Depende de: T-065. Hecho cuando: carga del zip en portal de prueba es aceptado sin errores (registro con captura).
- [ ] **T-067** — Medición de rendimiento y optimización ligera: tiempo validación individual (<30s, CA-NF-01) y generación zip (<60s CA-NF-02); ajuste de ReadFilter y batches si excede.
  Refs: CA-NF-01/02, RNF-003..005, RIES-03. Depende de: T-065, T-041. Hecho cuando: mediciones archivadas en H0 doc y ajustes reportados.

# FASE F — Migración histórica 2024-2026 (FASE-5 de PLAN, paralelizable con FASE E tras T-029)

**Objetivo:** importar histórico real desde `BD/` al nuevo modelo.
**Resultado esperado:** comando idempotente con conteos igualados y establecimiento_series sembrado.
**Condición de cierre de fase:** T-072 con conteo accepted + doble ejecución sin duplicar.

- [ ] **T-068** — Comando `rem:migrar --desde --hasta --ruta`: esqueleto con pipeline por pasos (catálogos→datos/control→hist→series), progreso y logging de pasos reprocesables.
  Refs: RF-024, RN-048. Depende de: T-013, T-025, T-056. Hecho cuando: comando corre en modo dry-run sin efectuar cambios y reporta pasos.
- [ ] **T-069** — Paso catálogos: reutiliza seed (T-025) idempotente (no duplica si ya existe), reporta tablas.
  Depende de: T-068. Hecho cuando: re-ejecutar no cambia conteos.
- [ ] **T-070** — Paso datos/control por serie/año: copiar primero los `DatosSERIE*`/`ControlSERIE*` a `storage/rem/migracion/` (verificar hash SHA-256 del original antes y después: originales intocados), luego lectura COM sobre la copia en bloques transaccionales por serie/año, reanudable, con normalización `,` → `.` (RN-037) y omisión de bases 2012 (fuera del alcance del MVP).
  Refs: RF-024, RN-037, LIM-024, RIES-12. Depende de: T-068, T-006. Hecho cuando: conteos destino por serie/año = fuente y comando puede reanudar tras corte manual simulado.
- [ ] **T-071** — Paso hist_carga + `establecimiento_series` derivado del histórico real (RN-038, RN-045) — resuelve funcionalmente PEND-008 con datos, no con lista manual.
  Depende de: T-070. Hecho cuando: cada establecimiento tiene exactamente las series con históricos y su flag `requerida` coherente.
- [ ] **T-072** — Verificación final: conteos origen vs destino por establecimiento/serie/año (RN-049), reporte con faltantes (LIM-024) y prueba de doble ejecución (RN-047).
  Refs: CA-024, CA-M6-EXTRA. Depende de: T-069..T-071. Hecho cuando: conteo total match y 2a ejecución sin incremento de filas.

# FASE G — Administración completa (FASE-6 de PLAN)

**Objetivo:** gestión operativa completa necesaria para el portal.
**Resultado esperado:** usuarios, cuentas, ventanas con feriados, series, servicio, tema, backups operativos.
**Condición de cierre de fase:** T-085 con CA-017..CA-023 verifers + primer backup diario exitoso.

- [ ] **T-073** — Recurso Usuarios internos: create/edit/activate/deactivate/password reset + visualizar MFA status.
  Refs: RF-017, CA-017, LIM-022. Depende de: T-019, T-023. Hecho cuando: CA-017 (usuario creado loguea con rol) manual + feature test.
- [ ] **T-074** — Recurso Cuentas establecimiento: titular+suplente por estab (RN-002), desactivación, reset de clave, login obligatorio activo (LIM-021).
  Refs: RF-018, CA-018, RN-039..041. Depende de: T-073, T-014. Hecho cuando: no permite crear tercero activo o desactivar ambos dejando el establecimiento sin acceso sin aviso.
- [ ] **T-075 ⚠** — `FeriadosClient` + job anual de sincronización de feriados con API y precarga manual; opción de re-seed desde admin.
  Refs: RN-009, RN-042, RNF-020, PD-PLAN-02. Depende de: T-026. Bloqueo parcial: PD-PLAN-06 fuente API específica no elegida — se implementa con provider feriadosapp.cl provisional y schema tolerante. Hecho cuando: job anual registra feriados próxima año y admin puede editar (RK: editable).
- [ ] **T-076** — Recurso VentanasCarga: create/edit (apertura/cierre/habilitada/notas), control manual abrir/cerrar/prorrogar con registro de autoría (RN-043).
  Refs: RF-019, CA-019, RN-042..044, LIM-016. Depende de: T-026, T-014. Hecho cuando: ventana marzo 2026 creada+editada correcta y auditada.
- [ ] **T-077** — `HabilCalculator` + `VentanaService` con tests de calendario (fines de semana, feriados sembrados, feriado regional) y calendarios 2025-2027.
  Refs: RN-042..044, RN-055, RN-064. Depende de: T-076. Hecho cuando: suite unit calendar >20 casos, incluyendo cálculo día 3 hábil (RN-064).
- [ ] **T-078** — Página calendario del portal: mes vigente, marcado feriado, estado ventana, "Próxima apertura: ..." (RN-051); historial siempre accesible aunque cerrada (RN-052).
  Refs: RF-026, CA-026. Depende de: T-077, T-021. Hecho cuando: CA-026 manual con feriados visibles y estado correcto.
- [ ] **T-079** — Recurso Series por establecimiento: define requeridas/opcionales por estab (seed de migración T-071) editable.
  Refs: RF-020, CA-020, RN-045. Depende de: T-071 (o seed manual si migración no disponible). Hecho cuando: al menos 1 caso borrado/re-agregado se persiste y refleja en el select del portal.
- [ ] **T-080** — Recurso Servicio activo: cambiar idservicio con doble confirmación (RN-046) + aviso de impacto global.
  Refs: RF-021, CA-021. Depende de: T-023. Hecho cuando: cambia y el panel+portal re-filtre por nuevo servicio (test con 23 → mock 24).

- [ ] **T-081** — Tema visual completo Osorno: paleta institucional, logo en ambos paneles, selector dark/light persistente.
  Refs: RF-022, CA-022, CA-M5-EXTRA. Depende de: T-027. Hecho cuando: CA-022 + CA-M5-EXTRA visibles sin recargar (persistencia Livewire).
- [ ] **T-082** — Visor de auditoría (activitylog con filtros acción/usuario/rango) + página "Estado del sistema": cat_software.version vs sync último, estado backups, health worker/cola.
  Refs: RF-023, CA-023, LIM-023. Depende de: T-028. Hecho cuando: CA-023 búsqueda funciona y estado sistema refleja la última sync.
- [ ] **T-083** — Backups automáticos: `BackupDatabaseJob` (mysqldump 02:30, retención 30 días) + copia de directorio archivos + `notificaciones` tabla para falla con alerta en dashboard.
  Refs: RNF-010/011, LIM-023, CA-NF-03. Depende de: T-015, T-009. Hecho cuando: primer backup diario verificado y tamano >0, restauración no still yet (T-084).
- [ ] **T-084** — Runbook restauración MySQL + archivos con simulación de restauración práctica (volcar en BD temporal e inspeccionar).
  Refs: CA-NF-03 (restore probado). Depende de: T-083. Hecho cuando: doc con comando probado + captura de conteo post-restore.
- [ ] **T-085** — Suite feature admin: CA-017..CA-023 así como endpoints de policy (rol interno solo).
  Depende de: T-073..T-082. Hecho cuando: tests an green y checklist manual de estos CA cerrada.

# FASE H — Portal de recepción (FASE-7 de PLAN)

**Objetivo:** ciclo completo del establecimiento con aislamiento y trazabilidad.
**Resultado esperado:** portal seguro usando el mismo motor con versionado/observaciones/cumplimiento.
**Condición de cierre de fase:** T-095 E2E verde con muestra de 10 establecimientos + verificación de seguridad lista.

- [ ] **T-086** — Hardening vhost portal final: vhost dedicado subdominio, redirección forzosa HTTPS+HSTS, bloqueo RewriteRule de `/backoffice/*` y otros paths internos desde este vhost, headers CSP básico.
  Refs: RN-015, RN-016, CA-SEG-02/03. Depende de: T-021, T-002. Hecho cuando: HTTP→HTTPS 301, `/backoffice` da 404 desde el vhost público (curl) y el panel interno sigue accesible en intranet.
- [ ] **T-087** — Rate limiting + bloqueo institucional en portal: límite >10 req/min → bloqueo 15 min (CA-SEG-04) + bloqueo tras N intentos de login fallidos por usuario.
  Refs: RF-025, CA-SEG-04, LIM-021. Depende de: T-086 (vhost), T-021. Hecho cuando: prueba automatizada da el bloqueo esperado y desbloquea tras expiración.
- [ ] **T-088** — Activación MFA en producción para ADMIN/SUPERVISOR con reset solo Admin y flujo enroll TOTP (una vez T-022 completada).
  Refs: RN-014, CA-SEG-05, LIM-022. Depende de: T-022. Hecho cuando: login admin sin TOTP falla en producción y con TOTP pasa; reset vía admin funciona.
- [ ] **T-089** — Página Cargar series: select de series **requeridas** de su establecimiento (T-079), drag&drop de archivo, validación server-side de ventana habilitada (RN-044) y serie asignada (RN-053/LIM-015), cálculo `en_plazo` al momento (RN-055/LIM-016), crea `recepcion+version` y encola job.
  Refs: RF-027, CA-027. Depende de: T-077, T-038, T-079, T-088. Hecho cuando: CA-027 manual + feature test (fuera ventana rechaza, no asignada rechaza server-side).

- [ ] **T-090** — Resultado validación inmediata con polling (`wire:poll`): 3 secciones con textos es-CL exactos (hoja/línea/columna/valor) cuando `con_errores`.
  Refs: RF-028, CA-028, RNF-003. Depende de: T-089, T-038. Hecho cuando: archivo con error en B5 hoja A01 muestra el mensaje exacto (tomado del catálogo de mensajes).
- [ ] **T-091** — Reenvío corregido versión+1 con historial de versiones completo y cronología de observaciones.
  Refs: RF-029, CA-029, RN-056/057, RN-062. Depende de: T-090, T-053. Hecho cuando: v2 sobre v1 visible en historial con timestamps y validación repetida.
- [ ] **T-092** — Segunda subida fuera de plazo: una única por serie/mes/año (RN-058/LIM-014), flag `es_segunda_subida`, `en_plazo=false`, bloquea tercera; flujo válido solo tras quedar validada y esperar revisión del operador, sin autoconsolidar (RN-060).
  Refs: RF-030, CA-030, RN-010/011. Depende de: T-091. Hecho cuando: CA-030 + bloqueo de tercera subida funcionan (test).
- [ ] **T-093** — Historial propio (RF-032) + página Cumplimiento propio: matriz serie × mes con ✓/✗/⚠ basada en sus rates.
  Depende de: T-091. Hecho cuando: CA-032 manual con todas las versiones y matriz correcta.
- [ ] **T-094** — Verificación de aislamiento end-to-end (CA-SEG-01): cuenta A no puede resolver modelos del B vía panel (id manipulado en URL Livewire), file, ni export; pruebas automatizadas de policies.
  Depende de: T-018, T-089. Hecho cuando: suite roja si el aislamiento se rompe (regression guard).

- [ ] **T-095** — E2E flujo completo establecimiento CA-M7-EXTRA: login → calendario → carga serie → validación ok → operador acepta → consolidado → notificaciones correspondientes y eventos → historial v1 → 2da subida fuera de plazo → operador revisa y acepta v2 → historial muestra v1+v2.
  Depende de: T-089..T-093, T-054, T-098. Hecho cuando: checklist E2E corrido manual sin desvíos (registro con capturas).
- [ ] **T-096** — Revisión responsive mobile-first y accesibilidad AA del portal (contraste, focus visible, labels, touch targets ≥44px), ajustar CSS si hace falta.
  Refs: RNF-014, RNF-015, CA-NF-04. Depende de: T-089..T-093. Hecho cuando: auditoría Lighthouse ≥90 accesibilidad y test visual en móvil.
- [ ] **T-097** — Suite feature portal completa (CA-025..CA-032) con verificación de constraints únicos de estado y eventos.
  Refs: RF-025..RF-032. Depende de: T-086..T-094. Hecho cuando: `php artisan test --filter=Portal` en verde.
  Depende de: T-086..T-094. Hecho cuando: `php artisan test --filter=Portal` en verde.

# FASE I — Notificaciones, informes restantes y cierre (FASE-8 de PLAN)

**Objetivo:** automatizar comunicación, completar informes y habilitar transición oficial.
**Resultado esperado:** notificaciones activas, informes completos, batería de rendimiento, checklist GO preparado.
**Condición de cierre de fase:** T-108 con GO del equipo REM para transición (2 ciclos en paralelo).

- [ ] **T-098 ⚠** — `NotificationRouter` multi-canal (SMTP mail + FTP) con configuración por `.env` (RN-063).
  Refs: TEC-12, RN-063, PEND-007. Bloqueo parcial: credenciales FTP exactas pendientes (PD-PLAN-03); SMTP funciona de referencia. Hecho cuando: router envía por canal activo y registra intento/fallo en `notificaciones_enviadas`.
- [ ] **T-099** — Confirmación post-carga inmediata (email/plantilla es-CL con fecha, versión, estado en_plazo/fuera de plazo/2da subida, resumen de validación correctos/errores/control) + copia de comprobante a FTP si configurado.
  Refs: RF-033b, RN-018, CA-033. Depende de: T-098. Hecho cuando: mail FAKE en test contiene encabezados correctos; prueba real manual con SMTP institucional cuando disponible.
- [ ] **T-100** — `NotificarRecordatorioJob` programado: día 3 hábil (T-077) → calcular pendientes (series requeridas sin `aceptada/validada` para el mes) → envío a titular+suplente activos.
  Refs: RF-033a, RN-064/065, FLUJO-09. Depende de: T-077, T-098. Hecho cuando: feature test encola el envío para un conjunto stub de pendientes y solo a cuentas activas.
- [ ] **T-101** — Registro y visibilidad de notificaciones: tabla auxiliar para operador/admin (env maritime: tipo, canal, fecha, estado).
  Depende de: T-099, T-100. Hecho cuando: admin puede consultar quién fue notificado y cuándo.
- [ ] **T-102** — Informe comparativos (entre establecimientos / entre meses por rendimiento) [RF-010 CA-010].
- [ ] **T-103** — Detección de duplicidad en `datos` [RF-011 CA-011].
- [ ] **T-104** — Auditorías de datos: Prestaciones / Decimales / Valor&Texto (reproducir reglas ValidadorRem sobre `datos`) [RF-012 CA-012].
- [ ] **T-105** — Suite nocturna de rendimiento: carga masiva 20 archivos (<5 min RNF-004) + generación zip año completo (<60s RNF-005) con reportes — vigila desviaciones de los time budgets.
  Refs: RNF-004/005, CA-NF. Depende de: T-067. Hecho cuando: corre en local con reportes archivados y sin regression decisiones.
- [ ] **T-106** — Pasada completa de seguridad: checklist secrets, headers, políticas, logs completos (CA-SEG-06), revisión de backups y permisos de archivos en reposo, sin agregados nuevos. Incluye verificar: (a) `git status` limpio de `BD/` (regla `/BD/` en `.gitignore`, doc `docs/BD-LOCAL.md`) y (b) que `BD/` no es accesible vía Apache (fuera de `public/`).
  Refs: CA-SEG-01..06, RNF-017. Depende de: T-086..T-088, T-083. Hecho cuando: checklist firmada manualmente con evidencias.
- [ ] **T-107** — Guías mínimas de uso: manual operativo equipo REM (PDF/web) y guía corta de establecimiento con FAQ de errores.
  Refs: RNF-016, RIES-07. Hecho cuando: documentos ubicados en `/docs` y enlazados desde el portal FAQ.
- [ ] **T-108** — Checklist GO de transición: piloto con 10 establecimientos durante 1 ciclo + verificación de criterios de transición (2 ciclos sin divergencias + 1 zip aceptado por Minsal) + registro del criterio de corte de ValidadorRem.
  Refs: AGENT §9 #: 10, OBJ-01..09, RIES-07. Depende de: T-095, T-066, T-105, T-106. Hecho cuando: checklist firmada por equipo REM.
- Consideración sobre transición La ventanaWAIT en paralelo no se planifica aquí como tarea de construcción (fase operacional); validación final del criterio de corte. Task A-108 lo cubre.

---

# Verificación de cobertura y grafo de dependencias

##◇ Grafo de dependencias (voz condensada)

```
T-001..T-011 (H0 serie)                  → GO técnico
  ├─ T-003..T-006 (Laravel, pocs)        ─┐
  └─ T-004..T-009 (POCs cierres)   ───────┴→ T-012..T-016 (migraciones) → T-017 model → T-018/19 → paneles T-020/21
T-020/21 → seeders T-023..T-026 → T-027..T-028 → T-029 (cierre zócalo)
  ├─ (rama motor)   T-030..T-037 → T-038 → T-039 → T-040 (marco) → T-041 paridad …
  ├─ (paralela)     T-042 (refs zip)  [puede ejecutarse cuando T-006]
T-038 → T-043 (UI carga) … T-044 → T-045 → T-046 (consolidar) → T-047..T-051 → T-052..T-053 → T-054 → T-055 cierre fase D
T-055 → T-056..T-062 (integración) ┐
                                   ├─→ T-063..T-066 (generación+paridad zip) ─ Hito H3
T-042 + T-041 → T-065 paridad zip ┘

T-029 → (paralelo) T-068..T-072 (migración, con T-006 y T-056)
T-029 → T-073..T-085 (admin) → T-086..T-097 (portal, usa motor T-038 y consolidación T-054)
T-085 + T-097 → T-098..T-101 (notif) → T-102..T-108 (cierre) → GO de transición (T-108)
```

- **Ruta crítica:** T-001 → T-005/T-006 → T-012 → T-017 → T-038 → T-046 → T-063 → T-065 (H3) y sigue portal T-095 → GO.
- **Paralelismos seguros:** (1) FASE E (Minsal) puede partir después de T-055; (2) FASE F (migración) puede correr desde T-029; (3) UI de FASE G puede iniciar durante FASE E/F; (4) fixtures zip T-042 independiente tras T-006.
- **Círculo detectado por diseño PLAN:** motor necesita catálogos (que se obtiene del mismo Minsal). Resolución adoptada: seed inicial por .mdb local (T-025) — sin dependencia de Sync online; Sync queda para refresco.
- No se detectaron ciclos en el grafo final. Ninguna tarea depende de una fecha posterior a la fija del plan.

## Cobertura de requisitos (SPECS)

| Grupo | Requisitos | Tareas |
|---|---|---|
| M1 carga/validación | RF-001..RF-005 | T-043..T-046, T-030..T-038 |
| M2 informes | RF-006..RF-013 | T-047..T-051, T-102..T-104 |
| M3 Minsal gen | RF-014 | T-063..T-066 |
| M4 integración | RF-015/RF-016 | T-056..T-062 |
| M5 admin | RF-017..RF-023 | T-073..T-085 |
| M6 migración | RF-024 | T-068..T-072 |
| M7 portal | RF-025..RF-033 | T-086..T-097, T-099..T-101 |

**Ref: reglas (RN)** — RN-001 (T-018/T-094) · RN-002 (T-074) · RN-003/004 (T-031/T-043) · RN-009 (T-026/T-075) · RN-010..RN-020 (motor T-033..T-037) · RN-021/022 (T-046) · RN-023/024 (T-047) · RN-025..028 (T-063..T-065) · RN-029..038 (T-056..T-062, T-069..T-071) · RN-039..041 (T-074) · RN-042..044 (T-076..T-077) · RN-045 (T-071/T-079) · RN-046 (T-080) · RN-050..052 (T-078) · RN-053..055 (T-089) · RN-056/057 (T-091) · RN-058..060 (T-092) · RN-061/062 (T-053) · RN-063..065 (T-098..T-100).

**Criterios (CA)**: CA-001..CA-005 (T-043..T-046/T-055) · CA-006..CA-013 (T-047..T-051) · CA-014/CA-014+H3 (T-065/T-066) · CA-015/016 (T-060/T-062) · CA-017..023 (T-073..T-085) · CA-024 (T-072) · CA-025..033 (T-086..T-097, T-099..T-101) · CA-M*-EXTRA (T-041, T-051, T-066, T-081, T-072, T-095) · CA-SEG-01..06 (T-018/T-094, T-086..T-088, T-028/T-106) · CA-NF-01..04 (T-067, T-083, T-096, T-105).

**LIM** — Casos motor LIM-001..011 (T-039), flujo LIM-012..021 (T-044/T-046/T-076/T-089/T-092/T-074), infra LIM-019 (T-001/T-005), LIM-022..024 (T-088/T-083/T-070). Cubiertos.

## Cobertura de componentes PLAN (MOD)

MOD-01→T-012..T-018 · MOD-02→T-033..T-038 · MOD-03→T-031 · MOD-04→T-030 · MOD-05→T-009/T-038 · MOD-06→T-043..T-046 · MOD-07→T-047..T-051/T-102..T-104 · MOD-08→T-063..T-065 · MOD-09→T-056..T-062 · MOD-10→T-005/T-006/T-056 · MOD-11→T-073..T-082 · MOD-12→T-075..T-078 · MOD-13→T-068..T-072 · MOD-14→T-052/T-089..T-094 · MOD-15→T-098..T-101 · MOD-16→T-018/T-022/T-087/T-088/T-094 · MOD-17→T-083/T-084 · MOD-18→T-027/T-081. **Cobertura completa.**

## Bloqueos y decisiones pendientes que afectan tareas

| Tarea | Bloqueo | Impacto / Plan provisional |
|---|---|---|
| T-022 | PD-PLAN-01 (librería TOTP) | Interfaz + middleware implementables; elección concreta antes de portal |
| T-040/T-041 | Provisión de Excel reales del equipo REM | Suite intermedia con sintéticos; secuencial revalida con fixture completo |
| T-063 | PEND-009 (mes límite Registros.Mes) | Se adopta el criterio de ValidadorRem obtenido en H0; pendiente confirmación formal |
| T-075 | PD-PLAN-02 (API feriados definitiva) | Provider provisional feriadosapp.cl tolerantes; schema no cambia |
| T-098/T-099 | PEND-007 (datos FTP institucional) | SMTP disponible como canal; FTP se activará con configuración |

Ninguna otra tarea está impedida: el resto puede avanzar sin decidir estas pendientes.

## Problemas de atomicidad/consistencia detectados y resueltos al redactar

1. **Contradicción aparente RF-003 vs RF-005** (el job escribe en `datos` pero la consolidación hace DELETE+INSERT en `datos`): resuelto con **tablas de staging** (`datos_carga`, `datos_control_carga`, T-016), coherentes con RN-021/RN-022 y la lógica original de ValidadorRem (Temporal*.mdb → consolidación). Es una decisión técnica de detalle que respeta ambas especificaciones — no introduce funcionalidad nueva.
2. **Ciclo catálogos ↔ Minsal**: ya resuelto en PLAN §14.1 con seed por .mdb local; TASK lo materializa en T-025 antes del motor.
3. **Datos de paridad de zip**: no hay .zip de referencia; se usa el `.mdb` real de `BD/` como referencia de tablas (T-042/T-065), suficiente para CA-014 fila a fila. Los archivos de `BD/` son locales (no versionados — `docs/BD-LOCAL.md`); los fixtures exportados en `tests/Parity/fixtures/` se depuran de datos sensibles antes de versionarse.
4. **PEND-008 (lista establecimientos/series)**: derivable desde el histórico real (T-071), sin bloquear la migración.

## Checklist de verificación final

- [x] Todos los componentes del PLAN tienen tareas (MOD-01..18).
- [x] Los 33 RF tienen tareas; los 50 CA tienen tareas o verificaciones asociadas; 20 RNF cubiertos por tareas técnicas o de medición.
- [x] Existen tareas de pruebas (unit, feature, paridad, e2e, rendimiento, seguridad), no solo de código.
- [x] Casos límite LIM-001..024 asignados a tareas concretas de tests/UI/payloads.
- [x] Dependencias sin ciclos; orden ejecutable en cualquier momento — primera tarea pendiente siempre identifiable.
- [x] No hay tareas de funcionalidad fuera de alcance (FUERA-001..005 excluidas); no hay tareas de tamaño inmanejable (la más grande T-063 se subdivide opcionalmente por T-042/T-064/T-065 si hace falta).
- [x] Ninguna tarea marcada completada sin evidencia: **todas `[ ]`** porque la implementación no ha empezado.

---

# Confirmación

Archivo guardado en: `C:\xampp\htdocs\www\CargasREM\specs\TASK.md`.

**Estado verificado**: 0 completadas · 108 pendientes · 5 parcialmente bloqueadas por decisiones externas (T-022, T-041, T-063, T-075, T-098).
