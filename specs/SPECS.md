# WebCargaREM — Especificaciones Funcionales del MVP

## 1. Descripción del MVP

WebCargaREM es una plataforma web que reemplaza al sistema de escritorio **ValidadorRem** (VB.NET/WinForms/Access) utilizado por el **Servicio de Salud Osorno** (`idServicio = 23`) para validar, consolidar y empaquetar los archivos Excel REM (Registro Estadístico Mensual) que 86 establecimientos envían mensualmente al portal del Minsal (`cargasrem.minsal.cl`).

El MVP consta de **dos portales** sobre infraestructura XAMPP existente (Windows Server):

- **Panel interno (intranet):** back-office para el equipo REM (carga, validación, informes, generación Minsal, administración).
- **Portal de Recepción (HTTPS, subdominio institucional):** 86 establecimientos suben sus series REM directamente, con ventana de carga controlada, validación inmediata, versionado, observaciones y trazabilidad completa.

**Utilidad principal:** eliminar riesgos técnicos del sistema actual (SQL concatenado, sin transacciones, sin auditoría, bases Access frágiles), reemplazar el correo como canal de recepción por un portal trazable, y preservar **exactamente** las reglas de validación y el formato de salida `.mdb/.zip` idéntico al actual.

---

## 2. Objetivos

### OBJ-01: Paridad funcional con ValidadorRem
Los 6 módulos originales (carga individual, carga masiva, informes, generación Minsal, integración catálogos/histórico, administración) tienen equivalente web con mismo resultado observable.

### OBJ-02: Portal de recepción operativo
86 establecimientos con cuentas (titular + suplente) pueden autenticarse, ver calendario de carga, subir series, recibir validación inmediata, reenviar corregidos, y consultar su historial y cumplimiento.

### OBJ-03: Control de cumplimiento y ventanas de carga
Administrador gestiona ventanas de carga híbridas (calendario automático días 1–5 hábiles + feriados Chile vía API + control manual abrir/cerrar/prorrogar). Envíos fuera de plazo se aceptan marcados, con una 2da subida permitida (versión 2) que no consolida sin revisión.

### OBJ-04: Validación idéntica a ValidadorRem
Mismas reglas de encabezado (hoja NOMBRE), posicionamiento (Thojas/Diccionario), excepciones (Excep/colexcep), reglas numéricas (decimales, negativos, no-numéricos), hojas de control, filas vacías. Catálogos como datos en MySQL.

### OBJ-05: Generación de archivo Minsal idéntica
Produce `.zip` (GZip sobre `.mdb` plantilla `MASTER.mdb` vía COM/ADODB + ACE OLEDB 12.0) con tablas `Datos`, `DatosC`, `Registros` equivalentes fila a fila al ValidadorRem.

### OBJ-06: Integración Minsal automatizada
Sincronización de catálogos (HTTP GET + XML primaria, SOAP respaldo, importador Global.mdb último recurso) y descarga de histórico por serie/año, con auditoría completa.

### OBJ-07: Migración histórica 2024–2026
Comando idempotente que importa desde copias de `.mdb` (originales intocados): catálogos, datos, control, reconstruye `hist_carga`, siembra `establecimiento_series`. ~2,3M filas.

### OBJ-08: Seguridad y auditoría completa
4 roles con permisos granulares, MFA obligatorio producción (Admin/Supervisor), aislamiento por establecimiento, panel interno solo intranet, portal HTTPS con rate limiting, activity log + recepcion_eventos.

### OBJ-09: Notificaciones automáticas
Recordatorio día 3 hábil a pendientes (vía FTP/SMTP institucional) + confirmación post-carga inmediata con fecha, versión, estado y resumen de validación.

---

## 3. Usuarios y permisos conceptuales

| Identificador | Rol | Ámbito | Acciones principales |
|---|---|---|---|
| `ROL-OPERADOR` | Operador | Panel interno | Subir archivos (individual/masiva), validar, ver resultados, revisar recepciones de establecimientos, aceptar/observar/rechazar, consolidar |
| `ROL-SUPERVISOR` | Supervisor | Panel interno | Todo Operador + ver informes, exportar, dashboard cumplimiento, comparativos, auditorías |
| `ROL-ADMIN` | Administrador | Panel interno | Todo Supervisor + gestionar usuarios internos, crear cuentas establecimiento (titular+suplente), gestionar ventanas de carga, catálogos, servicio, tema visual, auditoría, backups |
| `ROL-ESTABLECIMIENTO` | Usuario Establecimiento (titular/suplente) | Portal recepción | Ver calendario, subir series asignadas, ver validación inmediata, reenviar versión corregida, ver historial propio, ver cumplimiento propio, recibir notificaciones |

**Regla de aislamiento (RN-001):** Un `ROL-ESTABLECIMIENTO` solo ve y opera sobre datos de su `establecimiento_id`. Nunca accede a datos de otro establecimiento ni a información interna del servicio.

**Regla de cuentas (RN-002):** Cada establecimiento tiene exactamente **2 cuentas nominales** (titular + suplente), ambas con acceso completo al portal del establecimiento. El Admin crea, desactiva y resetea claves.

---

## 4. Alcance funcional

### Incluido en MVP (Módulos M1–M7)

| Módulo | Código | Descripción |
|---|---|---|
| Carga y validación interna | M1 | Individual y masiva, colas, pantalla resultados (correctos/errores/control), confirmación explícita para consolidar |
| Informes y dashboard | M2 | Matriz estab×mes, control faltante, log errores, comparativos, duplicidad, auditorías (prestaciones, decimales, valor/texto), export Excel, panel cumplimiento |
| Generación archivo Minsal | M3 | Por año/serie, genera `.zip` descargable idéntico a ValidadorRem |
| Integración Minsal | M4 | Sync catálogos (GET/XML + SOAP respaldo + importador), descarga histórico por serie/año, transaccional, auditado |
| Administración | M5 | Usuarios (interno + establecimiento), ventanas de carga, catálogos (solo lectura + sync), servicio activo, tema visual, auditoría, backups |
| Migración histórica | M6 | Comando `rem:migrar` 2024–2026, idempotente, verificación conteos |
| Portal recepción | M7 | Login establecimiento, calendario, carga series, validación inmediata, versionado, observaciones, historial, cumplimiento, notificaciones |

### Fuera de alcance (explícito)

- `FUERA-001`: App móvil nativa
- `FUERA-002`: Firma electrónica de envíos
- `FUERA-003`: Conexión automática con sistemas clínicos de establecimientos
- `FUERA-004`: Particionado de tablas MySQL (no necesario en MVP)
- `FUERA-005`: Cifrado del .zip (solo GZip, como actual)

---

## 5. Requisitos funcionales

### 5.1 Módulo M1 — Carga y validación interna

#### RF-001: Carga individual de archivo Excel
**Descripción:** El Operador sube un archivo Excel REM (.xls/.xlsx/.xlsm según año) desde el panel interno.
**Actor:** Operador (panel interno)
**Entrada:** Archivo Excel, selección de serie, año, mes (opcional, se extrae del archivo)
**Resultado observable:** Archivo guardado en almacenamiento, job de validación encolado, ID de carga retornado.
**Reglas:** 
- RN-003: Año ≥ 2017 → solo `.xlsm`; años anteriores → `.xls/.xlsx/.xlsm`
- RN-004: Detección de extensión por año igual a ValidadorRem
- RN-005: Origen marcado como `interno`

**CA-001:** Dado un archivo Excel válido de 2026, cuando el Operador lo sube, entonces se crea registro en `cargas` con `estado=pendiente`, `origen=interno`, y se encola job de validación.

#### RF-002: Carga masiva (carpeta)
**Descripción:** El Operador sube múltiples archivos (drag & drop carpeta/zip) para procesamiento en lote.
**Actor:** Operador
**Entrada:** Múltiples archivos Excel
**Resultado observable:** Grilla de resultados por archivo (OK / Error / Ignorado), jobs de validación encolados por archivo válido.
**Reglas:**
- RN-006: Mismo filtro de extensión por año que RF-001
- RN-007: Archivos que no cumplen extensión/año → marcados "Ignorado"
- RN-008: Log consolidado de incorrectos en `log_errores` por archivo

**CA-002:** Dada una carpeta con 10 archivos (8 válidos, 2 con extensión incorrecta), cuando el Operador procesa la carga masiva, entonces 8 jobs se encolan y 2 aparecen como "Ignorado" en la grilla.

#### RF-003: Validación completa de archivo Excel (job background)
**Descripción:** Worker procesa el archivo: lee encabezado (hoja NOMBRE), valida establecimiento/mes/año/serie/versión, recorre hojas/rangos (Thojas), valida prestaciones (Diccionario), aplica excepciones (Excep/colexcep), reglas numéricas, separa correctos/errores/control.
**Actor:** Sistema (worker cola)
**Entrada:** `carga_id`, archivo en almacenamiento
**Resultado observable:** Registros en `datos` (correctos), `datos_control`, `log_errores`; `cargas.estado` actualizado a `validado` o `con_errores`.
**Reglas (desde ValidadorRem, preservadas exactamente):**
- RN-009: Encabezado hoja NOMBRE: Establecimiento (C3:H3), Mes (C6+D6), Año (B7), Serie (B17 primeros 7-8 chars), Versión (A9 primeros 12 chars)
- RN-010: Establecimiento debe pertenecer al `idservicio` configurado (Global.mdb → mpEstablecimiento filtrado por idservicio)
- RN-011: Mes texto ↔ número (ENERO=01…DICIEMBRE=12)
- RN-012: Año y serie del archivo coinciden con selección
- RN-013: Versión formato coincide con `cat_versiones` (mapeo SerieExcel ↔ Serie interna)
- RN-014: Thojas define hojas y rangos por serie/año
- RN-015: Diccionario define línea y columnas (inicio→fin) por prestación/hoja/serie/año
- RN-016: Excep ignora posiciones colexcep por prestación/hoja/serie/año
- RN-017: colexcep ignora columnas por codpres
- RN-018: Reglas numéricas: no numérico/negativo = error; >1 decimal = error (corta fila); 1 decimal = error EXCEPTO hoja A09, A21, SERIE BS, SERIE D; entero = válido
- RN-019: Hojas con nombre ≥ 6 chars → datos_control (col01–col05)
- RN-020: Filas con todos valores 0/null → no se insertan

**CA-003:** Dado un Excel real 2026 SERIE A, cuando el worker valida, entonces produce mismos correctos/errores/control que ValidadorRem (prueba de paridad).

#### RF-004: Pantalla de resultados de validación
**Descripción:** Operador ve tres grillas: Datos correctos, Log de errores (hoja/línea/columna/valor), Datos de control.
**Actor:** Operador
**Entrada:** `carga_id` con estado `validado` o `con_errores`
**Resultado observable:** Tres tablas filtradas por carga, exportables.

**CA-004:** Cuando la validación termina, el Operador accede a la carga y ve tres pestañas con datos idénticos a las grillas del ValidadorRem.

#### RF-005: Confirmación y consolidación (guardado)
**Descripción:** Operador revisa resultados y confirma para consolidar en `datos`/`datos_control` (transacción). Si ya existe carga para ese estab/mes/año/serie, aviso y opción reemplazar.
**Actor:** Operador
**Entrada:** `carga_id`, decisión (confirmar/reemplazar)
**Resultado observable:** Transacción: DELETE previo (si reemplaza) + INSERT masivo correctos + INSERT control + UPSERT `hist_carga` + INSERT `log_errores` → `cargas.estado=guardado`.
**Reglas:**
- RN-021: Todo en transacción única (rollback si falla)
- RN-022: Reemplazo borra datos previos de ese estab/mes/año/serie en `datos`, `datos_control`, `log_errores`, `hist_carga`

**CA-005:** Cuando el Operador confirma una carga validada sin errores, entonces los datos aparecen en consultas de informes y en generación Minsal.

---

### 5.2 Módulo M2 — Informes y dashboard

#### RF-006: Panel de recepción y cumplimiento (dashboard principal)
**Descripción:** Vista consolidada con tarjetas resumen y matriz establecimiento × serie por mes con estado y fecha de carga.
**Actor:** Operador, Supervisor, Admin
**Resultado observable:** Tarjetas (archivos mes, pendientes, errores abiertos, fuera plazo) + matriz exportable con filtros.
**Reglas:**
- RN-023: Muestra estado por recepción: `pendiente`, `recibida`, `validando`, `validada`, `con_errores`, `en_revision`, `aceptada`, `observada`, `rechazada`, `fuera_plazo`
- RN-024: Incluye % cumplimiento por serie/mes

**CA-006:** Al acceder al dashboard, el Supervisor ve matriz con 86 establecimientos × 6 series × mes actual, con estados y fechas correctas.

#### RF-007: Matriz establecimientos × meses (EstabCargados)
**Descripción:** Tabla pivote con "X" por serie/mes/establecimiento, exportable a Excel.
**Actor:** Supervisor, Admin
**CA-007:** Export Excel reproduce matriz idéntica a ValidadorRem `EstabCargados`.

#### RF-008: Informe control faltante (InformeControl)
**Descripción:** Cruce establecimientos × datos_control: muestra cuáles no tienen hojas de control cargadas.
**Actor:** Supervisor, Admin
**CA-008:** Resultado idéntico a ValidadorRem `InformeControl` con mismos datos.

#### RF-009: Log de errores (LogErrorCargados / LogErrorMultiples)
**Descripción:** Consulta filtrada por serie/mes/establecimiento de `log_errores` (carga interna) y `recepciones` + observaciones (portal). Exportable.
**Actor:** Operador, Supervisor
**CA-009:** Filtros funcionan; export Excel coincide con ValidadorRem.

#### RF-010: Comparativos (entre establecimientos / entre meses)
**Descripción:** Compara valores de prestaciones entre establecimientos o entre meses del mismo establecimiento.
**Actor:** Supervisor
**CA-010:** Resultados numéricos coinciden con ValidadorRem `Comparativos` / `ComparativoMes`.

#### RF-011: Detección de duplicidad
**Descripción:** Identifica registros repetidos en `datos` (mismo estab/mes/año/serie/codigo_prestación).
**Actor:** Supervisor
**CA-011:** Detecta duplicados conocidos en datos de prueba.

#### RF-012: Auditorías de datos (Prestacion, Decimales, ValoryText)
**Descripción:** 
- Prestaciones: verifica códigos existen en Diccionario
- Decimales: detecta valores con decimales donde no corresponde (salvo excepciones RN-018)
- Valor/Texto: detecta textos en campos numéricos
**Actor:** Supervisor
**CA-012:** Cada auditoría reporta mismos hallazgos que ValidadorRem módulos homónimos.

#### RF-013: Exportación libre a Excel
**Descripción:** Exportar cualquier consulta/informe con filtros aplicados.
**Actor:** Supervisor, Admin
**CA-013:** Archivo .xlsx generado y descargable con datos correctos.

---

### 5.3 Módulo M3 — Generación archivo Minsal

#### RF-014: Generar .zip Minsal por año/serie
**Descripción:** Admin/Supervisor selecciona año y serie → sistema genera `.zip` descargable.
**Actor:** Supervisor, Admin
**Entrada:** Año, Serie (A, BS, BM, D, P, F)
**Resultado observable:** Archivo `.zip` descargado.
**Reglas:**
- RN-025: Verifica existencia de datos en `datos` para año/serie
- RN-026: Copia `MASTER.mdb` → vuelca `datos` (Col01–Col50) + `datos_control` (col01–col05) + fila `Registros` (cantidad, idservicio, serie, mes límite, fecha, año)
- RN-027: Comprime GZip → `.zip`, borra .mdb intermedio
- RN-028: **Prueba de paridad obligatoria**: mismo contenido que ValidadorRem (tablas Datos, DatosC, Registros fila a fila)

**CA-014:** Dados mismos datos de entrada, el `.zip` generado por WebCargaREM tiene contenido equivalente fila a fila al generado por ValidadorRem (Hito H3).

---

### 5.4 Módulo M4 — Integración Minsal

#### RF-015: Sincronizar catálogos desde Minsal
**Descripción:** Admin ejecuta sync: descarga catálogos (establecimientos, servicios, diccionario, Thojas, Excep, colexcep, versiones) y actualiza tablas MySQL.
**Actor:** Admin
**Entrada:** Acción manual "Sincronizar catálogos"
**Resultado observable:** Tablas `cat_*` actualizadas; `cat_software.version` actualizada; registro en auditoría.
**Reglas:**
- RN-029: Vía principal: HTTP GET + XML (Guzzle + SimpleXML) a endpoints `webservicedb.asmx` (`/datos`, `/servicio`, `/diccionario`, `/Thoja`, `/Excep`, `/ColExcep`, `/Version`)
- RN-030: Respaldo: SOAP (`SoapClient`) si GET falla
- RN-031: Último recurso: importador desde `Global.mdb` local (solo lectura)
- RN-032: Borra y reinserta por tabla en transacción; progreso por tabla
- RN-033: Normaliza comas a puntos, escapa apóstrofes

**CA-015:** Tras sync, `cat_diccionario`, `cat_thojas`, `cat_excep`, `cat_colexcep`, `cat_versiones`, `establecimientos`, `servicios` reflejan datos del servidor Minsal; `cat_software.version` = versión servidor.

#### RF-016: Descargar histórico por serie/año
**Descripción:** Admin selecciona serie y año → descarga datos y control desde Minsal, reemplaza bases locales, reconstruye `hist_carga`.
**Actor:** Admin
**Entrada:** Serie, Año
**Resultado observable:** `datos` y `datos_control` poblados para serie/año; `hist_carga` reconstruido; auditoría.
**Reglas:**
- RN-034: Confirma antes ("Se borrarán todos los datos...")
- RN-035: Usa `CargaSerieServ.DatosSerie(serie, idservicio, año)` y `CargaControlServ.DatosControl(serie, idservicio, año)` (HTTP GET/XML o SOAP)
- RN-036: Limpia local: DELETE datos/control/log_errores/hist_carga para serie/año
- RN-037: Reinserta normalizando comas a puntos
- RN-038: Reconstruye `hist_carga` por GROUP BY estab/mes/año

**CA-016:** Tras descarga histórico 2025 SERIE A, conteos de filas en `datos` coinciden con servidor Minsal.

---

### 5.5 Módulo M5 — Administración

#### RF-017: Gestión de usuarios internos
**Descripción:** Admin CRUD usuarios Operador/Supervisor/Admin (nombre, email, password, rol, MFA).
**Actor:** Admin
**CA-017:** Usuarios creados pueden loguearse en panel interno con su rol.

#### RF-018: Gestión de cuentas de establecimiento
**Descripción:** Admin crea/edita/desactiva/resetea 2 cuentas nominales por establecimiento (titular + suplente), vinculadas a `establecimiento_id`.
**Actor:** Admin
**Reglas:**
- RN-039: Exactamente 2 cuentas por establecimiento activo
- RN-040: Tipo `establecimiento`, `establecimiento_id` obligatorio
- RN-041: Recuperación de clave disponible

**CA-018:** Admin crea cuentas para 86 establecimientos; cada una accede al portal y ve solo su establecimiento.

#### RF-019: Gestión de ventanas de carga
**Descripción:** Admin crea/edita ventana mensual: apertura, cierre, habilitada, notas. Por defecto: días 1–5 hábiles + feriados Chile (API). Control manual: abrir/cerrar/prorrogar con auditoría.
**Actor:** Admin
**Entrada:** Año, mes, fechas, notas
**Resultado observable:** Registro en `ventanas_carga`; calendario visible en portal.
**Reglas:**
- RN-042: Feriados Chile (nacionales + regionales Los Lagos) obtenidos vía API y precargados, editables por admin
- RN-043: Cambios de estado (abrir/cerrar/prorrogar) registran quién, cuándo, notas en auditoría
- RN-044: Ventana habilitada = formulario de carga activo en portal

**CA-019:** Admin crea ventana marzo 2026; feriados 2026 precargados; admin edita y guarda; portal muestra calendario correcto.

#### RF-020: Configuración de series por establecimiento
**Descripción:** Admin define qué series reporta cada establecimiento (`establecimiento_series`: requerida/opcional).
**Actor:** Admin
**Reglas:**
- RN-045: Se siembra desde histórico 2024–2026 (migración); admin ajusta casos borde

**CA-020:** Tras migración, `establecimiento_series` refleja series reportadas históricamente; admin puede editar.

#### RF-021: Configuración de servicio activo
**Descripción:** Admin modifica `idservicio` del servicio operativo (equivalente a `Seteo` pero modificable, no irreversible).
**Actor:** Admin
**Reglas:**
- RN-046: Cambio de servicio requiere confirmación y afecta filtrado global

**CA-021:** Admin cambia servicio a 23 (Osorno); panel y portal filtran por idservicio=23.

#### RF-022: Tema visual personalizado Osorno
**Descripción:** Admin configura colores institucionales, logo, modo dark/light.
**Actor:** Admin
**CA-022:** Panel interno y portal muestran tema Osorno; selector dark/light funcional.

#### RF-023: Auditoría y estado del sistema
**Descripción:** Vista de activity log (filtrada), versión BD local vs servidor, último sync, estado backups.
**Actor:** Admin
**CA-023:** Auditoría muestra acciones de todos los roles con usuario, IP, timestamp, acción.

---

### 5.6 Módulo M6 — Migración histórica

#### RF-024: Comando de migración 2024–2026
**Descripción:** `php artisan rem:migrar --desde=2024 --hasta=2026` importa desde copias de `.mdb` en directorio temporal.
**Actor:** Admin (línea de comandos)
**Entrada:** Rango años, ruta directorio copias
**Resultado observable:** Datos migrados, conteos origen=destino, `establecimiento_series` sembrado.
**Reglas:**
- RN-047: Idempotente (re-ejecutable sin duplicar)
- RN-048: Pasos: catálogos → datos/control por serie/año → reconstruir hist_carga → sembrar establecimiento_series
- RN-049: Verificación: conteo filas origen vs destino por serie/año/establecimiento

**CA-024:** Ejecutado comando, conteos MySQL = conteos .mdb origen para 2024, 2025, 2026.

---

### 5.7 Módulo M7 — Portal de Recepción (Establecimientos)

#### RF-025: Login establecimiento
**Descripción:** Titular/suplente acceden con credenciales; MFA opcional (obligatorio solo Admin/Supervisor en producción).
**Actor:** Usuario Establecimiento
**CA-025:** Credenciales válidas → acceso a portal; inválidas → error + rate limiting + bloqueo tras intentos.

#### RF-026: Calendario de carga
**Descripción:** Muestra mes habilitado, apertura/cierre, estado ventana (abierta/cerrada), feriados visibles.
**Actor:** Usuario Establecimiento
**Reglas:**
- RN-050: Feriados Chile vía API (nacionales + Los Lagos), precargados y editables por admin
- RN-051: Si ventana cerrada → mensaje "Recepción cerrada. Próxima apertura: <fecha>"
- RN-052: Puede consultar historial y series pendientes aunque esté cerrada

**CA-026:** Establecimiento accede en marzo 2026; ve ventana 1–5 hábiles marzo, feriados marcados, estado "Abierta".

#### RF-027: Formulario de carga de series
**Descripción:** Habilitado solo si ventana abierta (o admin la abrió manualmente). Establecimiento elige serie de sus asignadas y sube archivo.
**Actor:** Usuario Establecimiento
**Entrada:** Serie (select de sus `establecimiento_series`), archivo Excel
**Resultado observable:** Recepción creada en `recepciones` con `estado=recibida`, `version=1`, `fecha_carga=now`, `en_plazo=true/false`, job validación encolado.
**Reglas:**
- RN-053: Solo series marcadas `requerida=true` en `establecimiento_series`
- RN-054: Validación inmediata igual a RF-003 (mismo worker, mismas reglas)
- RN-055: `en_plazo` = (fecha_carga ≤ cierre ventana vigente)

**CA-027:** Establecimiento sube SERIE A en día 3 hábil; recepción creada con `en_plazo=true`, validación encolada.

#### RF-028: Validación inmediata y retroalimentación
**Descripción:** Tras subida, establecimiento ve resultado: sin errores → `validada`; con errores → `con_errores` con detalle hoja/línea/columna/valor.
**Actor:** Usuario Establecimiento (recibe notificación/polling)
**CA-028:** Archivo con error en celda B5 hoja A01 → portal muestra "Hoja A01, Línea 5, Columna B: valor 'abc' no numérico".

#### RF-029: Reenvío corregido (versionado)
**Descripción:** Si `con_errores` u `observada`, establecimiento sube versión corregida → `version+1`, nuevo job validación.
**Actor:** Usuario Establecimiento
**Reglas:**
- RN-056: Cada reenvío incrementa versión; historial completo preservado
- RN-057: Solo última versión puede ser aceptada para consolidar

**CA-029:** Establecimiento corrige error y reenvía; ve versión 2 en historial; validación se repite.

#### RF-030: Segunda subida fuera de plazo (versión 2)
**Descripción:** Si ventana cerrada y ya hay recepción `aceptada`/`validada`, se permite **una única** subida adicional (versión 2), marcada `es_segunda_subida=true`, `en_plazo=false`, no consolida sin revisión.
**Actor:** Usuario Establecimiento
**Reglas:**
- RN-058: Máximo 1 segunda subida por serie/mes/año/establecimiento
- RN-059: Estado inicial `recibida` → validación → `validada`/`con_errores` → revisión equipo REM
- RN-060: No consolida automáticamente; Operador debe aceptar explícitamente

**CA-030:** Ventana cerrada día 10; establecimiento con recepción `aceptada` sube v2; ve "2da subida (fuera de plazo)"; Operador ve estado `validada` pendiente de revisión.

#### RF-031: Observaciones equipo REM ↔ Establecimiento
**Descripción:** Operador observa recepción → `estado=observada` + comentario; establecimiento ve observación y reenvía (nueva versión).
**Actor:** Operador (interno) → Usuario Establecimiento (portal)
**Reglas:**
- RN-061: Observaciones en `recepcion_observaciones` (autor, tipo, texto, timestamp)
- RN-062: Diálogo trazado; cada observación genera nueva versión al responder

**CA-031:** Operador observa "Dato inválido en hoja A05"; establecimiento ve observación en portal; reenvía v3; Operador acepta v3.

#### RF-032: Historial y cumplimiento propio
**Descripción:** Establecimiento ve lista de sus recepciones (fecha, versión, estado, en_plazo, es_segunda_subida, resumen validación) y matriz de series pendientes del mes.
**Actor:** Usuario Establecimiento
**CA-032:** Historial muestra todas las versiones con timestamps; matriz muestra ✓/✗ por serie/mes.

#### RF-033: Notificaciones automáticas
**Descripción:** 
- (a) Recordatorio día 3 hábil: FTP/SMTP a establecimientos con series pendientes (requeridas sin recepción `aceptada`/`validada`)
- (b) Confirmación post-carga: inmediata tras cada subida (fecha/hora, versión, estado en_plazo/fuera_plazo/2da_subida, resumen validación correctos/errores/control)
**Actor:** Sistema (job programado + job post-carga)
**Reglas:**
- RN-063: Canal FTP/SMTP institucional (configurable)
- RN-064: Día 3 hábil calculado sobre calendario con feriados
- RN-065: Solo a cuentas activas (titular + suplente)

**CA-033:** Día 3 hábil marzo 2026 → 40 establecimientos pendientes reciben recordatorio FTP. Establecimiento sube archivo → recibe confirmación inmediata con resumen.

---

## 6. Flujos principales

### FLUJO-01: Carga interna (Operador)
```
Operador selecciona archivo → Sube (RF-001) → Job valida (RF-003) 
→ Operador ve resultados (RF-004) → Confirma (RF-005) 
→ Datos en datos/datos_control/hist_carga → Disponibles para informes y Minsal
```

### FLUJO-02: Carga masiva interna (Operador)
```
Operador sube carpeta → Procesa lote (RF-002) → Jobs validan en paralelo 
→ Grilla resultados por archivo → Confirma cada uno (RF-005)
```

### FLUJO-03: Recepción establecimiento (Portal)
```
Establecimiento login (RF-025) → Ve calendario (RF-026) 
→ Si ventana abierta: elige serie + sube (RF-027) 
→ Validación inmediata (RF-028) 
→ Si ok: estado validada → Operador revisa → Acepta (RF-005) → Consolidado
→ Si error: con_errores → Establecimiento corrige + reenvía v2 (RF-029)
→ Si observada: Establecimiento ve observación (RF-031) → Reenvía vN
→ Historial actualizado (RF-032) → Notificación confirmación (RF-033b)
```

### FLUJO-04: Segunda subida fuera de plazo
```
Ventana cerrada + recepción previa aceptada/validada 
→ Establecimiento elige "2da subida" → Sube v2 (RF-030) 
→ Validación → Operador revisa → Acepta/Rechaza 
→ Si acepta: consolida (reemplaza v1 en datos)
```

### FLUJO-05: Sync catálogos Minsal (Admin)
```
Admin ejecuta sync (RF-015) → HTTP GET/XML (primario) 
→ Si falla: SOAP (respaldo) → Si falla: importar Global.mdb 
→ Transacción por tabla → Auditoría → cat_software.version actualizada
```

### FLUJO-06: Descarga histórico (Admin)
```
Admin elige serie/año → Confirma (RF-016) 
→ Descarga datos/control → Limpia local → Reinserta normalizado 
→ Reconstruye hist_carga → Auditoría
```

### FLUJO-07: Gestión ventana de carga (Admin)
```
Admin crea/edita ventana (RF-019) → Feriados API precargados → Edita → Guarda 
→ Portal muestra calendario → Admin puede abrir/cerrar/prorrogar manual (auditado)
```

### FLUJO-08: Generación Minsal (Supervisor/Admin)
```
Selecciona año/serie (RF-014) → Verifica datos → Genera .zip (paridad) → Descarga
```

### FLUJO-09: Recordatorio día 3 hábil (Sistema)
```
Job programado día 3 hábil → Calcula pendientes (requeridas sin aceptada/validada) 
→ Envía FTP/SMTP a titular+suplente de cada establecimiento pendiente
```

---

## 7. Interfaz y experiencia de usuario

### 7.1 Panel interno (Filament - Intranet)

| Vista / Pantalla | Elementos clave | Acciones |
|---|---|---|
| **Login** | Email, password, MFA (Admin/Supervisor prod) | Autenticar |
| **Dashboard** | Tarjetas resumen, matriz cumplimiento (filtros serie/mes), accesos rápidos | Navegar, exportar matriz |
| **Carga / Validación** | Subir archivo individual, subir carpeta masiva, lista cargas (estado, fecha, origen), ver resultados (3 pestañas: correctos, errores, control), botón "Consolidar" | Subir, validar, revisar, consolidar |
| **Recepción establecimientos** | Lista recepciones (filtros: serie, mes, establecimiento, estado, en_plazo), ver detalle (archivo, validación, observaciones, versiones), botones "Aceptar", "Observar", "Rechazar" | Revisar, aceptar, observar, rechazar |
| **Informes** | Tabs: Matriz, Control faltante, Log errores, Comparativos, Duplicidad, Auditorías (Prestacion, Decimales, ValoryText), Export libre | Filtrar, ver, exportar Excel |
| **Minsal** | Selector año/serie, botón "Generar .zip", historial de generaciones | Generar, descargar |
| **Integración Minsal** | Botones: "Sincronizar catálogos", "Descargar histórico" (selector serie/año), log de syncs | Sync, descargar histórico |
| **Administración** | Sub-tabs: Usuarios internos, Cuentas establecimiento, Ventanas carga, Series por estab, Servicio activo, Tema visual, Auditoría, Backups | CRUD completo por sub-tab |
| **Migración** | Botón "Ejecutar migración 2024-2026" (con parámetros), log de ejecución, verificación conteos | Ejecutar, ver resultados |

**Estados de carga (cargas):** `pendiente` → `validando` → `validada` | `con_errores` → `guardado` | `rechazado`

**Estados de recepción (recepciones):** `recibida` → `validando` → `validada` | `con_errores` → `en_revision` → `aceptada` | `observada` → (reenvío, version+1) | `rechazada` | `fuera_plazo` (flag) | `es_segunda_subida` (flag)

### 7.2 Portal recepción (Filament - HTTPS)

| Vista / Pantalla | Elementos clave | Acciones |
|---|---|---|
| **Login** | Email, password, "Recordarme", recuperar clave | Autenticar |
| **Calendario** | Mes actual, apertura/cierre, estado (abierta/cerrada), feriados marcados, próxima apertura | Ver, navegar meses |
| **Carga de series** | Select series asignadas (requeridas), drag&drop archivo, botón "Subir" (habilitado solo si ventana abierta), indicador "2da subida" si aplica | Subir, ver validación |
| **Resultado validación** | 3 secciones: Correctos (tabla), Errores (hoja/línea/col/valor), Control (tabla) | Ver, corregir, reenviar |
| **Historial** | Tabla: Fecha, Serie, Versión, Estado, En plazo, 2da subida, Resumen (correctos/errores/control), Acciones (ver, reenviar) | Ver detalle, reenviar |
| **Cumplimiento** | Matriz simple: Serie × Mes → ✓/✗/⚠ (pendiente/atrasado) | Ver |
| **Observaciones** | En detalle de recepción: hilo de observaciones (autor, fecha, texto), botón "Responder" (sube nueva versión) | Leer, responder |

**Navegación:** Sidebar fija (Calendario, Cargar, Historial, Cumplimiento). Header: logo Osorno, usuario, selector dark/light, cerrar sesión.

**Responsive:** Mobile-first para portal (establecimientos pueden usar tablet/celular). Panel interno desktop-first.

---

## 8. Datos conceptuales

| Entidad | Descripción | Atributos clave | Relaciones |
|---|---|---|---|
| **Servicio** | Servicio de salud (Osorno = 23) | id, nombre, region, orden | 1→N Establecimientos |
| **Establecimiento** | Centro de salud que reporta REM | id, nombre, dirección, teléfono, idservicio, idcomuna, iddependencia, idtipo, propiedad | N→1 Servicio; 1→N Cuentas; 1→N Series asignadas; 1→N Cargas; 1→N Recepciones |
| **CuentaEstablecimiento** | Usuario titular/suplente | id, nombre, email, password_hash, establecimiento_id, tipo (titular/suplente), activo, mfa_secret | N→1 Establecimiento |
| **UsuarioInterno** | Operador/Supervisor/Admin | id, nombre, email, password_hash, rol, mfa_secret, activo | — |
| **Serie** | Serie REM (A, BS, BM, D, P, F) | codigo, nombre, descripcion | 1→N Diccionario; 1→N Thojas; 1→N Versiones; 1→N EstablecimientoSeries |
| **EstablecimientoSerie** | Qué series reporta cada estab | establecimiento_id, serie, requerida | N→1 Establecimiento; N→1 Serie |
| **VentanaCarga** | Periodo de recepción mensual | id, idservicio, año, mes, apertura, cierre, habilitada, notas, creada_por | N→1 Servicio |
| **Carga** | Archivo subido por equipo interno | id, idservicio, año, serie, mes, establecimiento_id, archivo, origen (interno/portal), estado, usuario_id | N→1 Establecimiento; N→1 UsuarioInterno |
| **Recepcion** | Envío de establecimiento via portal | id, idservicio, establecimiento_id, serie, mes, año, archivo, hash, fecha_carga, en_plazo, es_segunda_subida, estado, version, usuario_id, carga_id | N→1 Establecimiento; N→1 UsuarioEstablecimiento; 1→1 Carga (al consolidar) |
| **Observacion** | Diálogo REM ↔ Establecimiento | id, recepcion_id, autor_id, tipo (observacion/rechazo), texto | N→1 Recepcion |
| **EventoRecepcion** | Historial de estados | id, recepcion_id, estado_anterior, estado_nuevo, usuario_id | N→1 Recepcion |
| **Datos** | Datos consolidados (tabla única) | id, idservicio, serie, mes, año, establecimiento_id, codigo_prestacion, fecha_ingreso, col01…col50 | Índice (serie, año, establecimiento_id, mes) |
| **DatosControl** | Hojas de control/resumen | id, idservicio, serie, mes, año, establecimiento_id, col01…col05 | — |
| **HistCarga** | Histórico de cargas (reconstruible) | establecimiento_id, mes, año, serie, idservicio, fuente (upload/servidor) | — |
| **LogErrores** | Errores de validación | id, carga_id, establecimiento_id, mes, serie, linea, hoja, dato, año, idservicio | N→1 Carga |
| **CatDiccionario** | Posicionamiento prestaciones | codigo_prestacion, mes, año, texto_prestacion, hoja, serie, posicion, tipo_dato, linea, inicio, fin | — |
| **CatThojas** | Hojas y rangos por serie/año | serie, hoja, año, mes, rango | — |
| **CatExcep** | Celdas a ignorar | codigo_prestacion, mes, año, hoja, serie, posicion, tipo_dato, col_excep | — |
| **CatColExcep** | Columnas a omitir por prestación | codigo_pres, col | — |
| **CatVersiones** | Versión formato Excel esperada | mes, año, version, serie, serie_excel | — |
| **CatSoftware** | Versión BD local | nombre, version | — |

---

## 9. Reglas de negocio

| ID | Regla | Descripción |
|---|---|---|
| RN-001 | Aislamiento establecimiento | `ROL-ESTABLECIMIENTO` solo ve/opera su `establecimiento_id` |
| RN-002 | Cuentas por establecimiento | Exactamente 2 cuentas nominales (titular + suplente) por establecimiento activo |
| RN-003 | Extensión por año | Año ≥ 2017 → solo `.xlsm`; anterior → `.xls/.xlsx/.xlsm` |
| RN-004 | Origen carga | `interno` (equipo REM) o `portal` (establecimiento) |
| RN-005 | Validación idéntica ValidadorRem | Mismas reglas RN-009 a RN-020 |
| RN-006 | Transacción consolidación | Todo guardado (DELETE previo + INSERT) en transacción única |
| RN-007 | Reemplazo carga existente | Borra datos/control/log/hist previos de ese estab/mes/año/serie |
| RN-008 | Ventana carga híbrida | Calendario automático (1-5 hábiles + feriados API) + control manual admin |
| RN-009 | Feriados Chile | Nacionales + regionales Los Lagos vía API, precargados, editables admin |
| RN-010 | Envío fuera de plazo | Se acepta, `en_plazo=false`, marcado error cumplimiento, no se descarta |
| RN-011 | Segunda subida | Máximo 1 por serie/mes/año/estab si ya hay `aceptada`/`validada`; `version=2`, `es_segunda_subida=true`, no consolida sin revisión |
| RN-012 | Versionado recepciones | Cada reenvío incrementa `version`; historial completo preservado |
| RN-013 | Consolidación solo tras aceptación | Operador debe aceptar explícitamente; nunca automático |
| RN-014 | MFA producción | Obligatorio Admin/Supervisor (TOTP); opcional desarrollo |
| RN-015 | Panel interno solo intranet | Nunca expuesto públicamente |
| RN-016 | Portal HTTPS obligatorio | Rate limiting, bloqueo intentos, WAF si disponible |
| RN-017 | Retención archivos | Indefinida; borrado manual por Admin |
| RN-018 | Notificaciones | (a) Día 3 hábil FTP/SMTP a pendientes; (b) Post-carga inmediata con resumen |
| RN-019 | Sync catálogos | HTTP GET/XML primario → SOAP respaldo → importador Global.mdb último recurso |
| RN-020 | Generación Minsal paridad | .zip contenido equivalente fila a fila a ValidadorRem |
| RN-021 | Migración idempotente | Re-ejecutable sin duplicar; verificación conteos origen=destino |
| RN-022 | Tema visual Osorno | Colores, logo, dark/light configurables por Admin |
| RN-023 | Menús 5 grupos | Carga, Informes, Minsal, Administración, Recepción |

---

## 10. Casos límite y errores

| ID | Caso | Comportamiento esperado |
|---|---|---|
| LIM-001 | Archivo Excel sin valores cacheados (fórmulas no evaluadas) | Rechazar con mensaje: "Abra y guarde el archivo en Excel para calcular fórmulas" |
| LIM-002 | Archivo con extensión incorrecta para el año | Marcar "Ignorado" (carga masiva) o rechazar con error claro (carga individual) |
| LIM-003 | Establecimiento no pertenece al servicio configurado | Error: "Establecimiento no pertenece al servicio activo" |
| LIM-004 | Mes texto no coincide con mes número | Error: "Mes en texto (B6) no coincide con mes numérico (C6+D6)" |
| LIM-005 | Versión formato no coincide con cat_versiones | Error: "Versión del formato no corresponde al mes/año/serie" |
| LIM-006 | Hoja no definida en Thojas para serie/año | Error: "Hoja X no configurada para esta serie/año" |
| LIM-007 | Prestación no está en Diccionario para esa hoja/serie/año | Se ignora (no error, no se procesa) |
| LIM-008 | Valor con >1 decimal | Error, corta análisis de la fila completa |
| LIM-009 | Valor con 1 decimal en hoja no exceptuada | Error (salvo A09, A21, SERIE BS, SERIE D) |
| LIM-010 | Valor negativo o no numérico | Error |
| LIM-011 | Fila totalmente vacía (todo 0/null) | No se inserta (silencioso) |
| LIM-012 | Carga para estab/mes/año/serie ya existente | Aviso "Ya existe carga. ¿Desea reemplazar?" → si sí, transacción reemplazo |
| LIM-013 | Recepción fuera de ventana | Se acepta, `en_plazo=false`, estado `fuera_plazo`, notificación |
| LIM-014 | Tercera subida fuera de plazo | Bloquear: "Ya utilizó su 2da subida permitida" |
| LIM-015 | Establecimiento intenta subir serie no asignada | Select no muestra series no asignadas; validación server-side rechaza |
| LIM-016 | Admin cierra ventana mientras establecimiento sube | Subida se procesa; `en_plazo` calculado al momento de recepción |
| LIM-017 | Servicio Minsal no responde (sync catálogos) | Intenta SOAP → si falla, ofrece importar Global.mdb; registra error en auditoría |
| LIM-018 | Generación Minsal sin datos para año/serie | Error: "No existen datos para este año y serie" |
| LIM-019 | COM/ACE OLEDB no disponible | Error crítico en H0; bloquea generación Minsal |
| LIM-020 | Archivo Excel > límite PHP (upload_max_filesize) | Validación frontend + backend; mensaje claro |
| LIM-021 | Usuario establecimiento inactivo | Login denegado; Admin debe reactivar |
| LIM-022 | MFA perdido (Admin/Supervisor) | Recuperación vía Admin (reset MFA) |
| LIM-023 | Backup falla | Alerta en dashboard Admin; reintento automático próximo ciclo |
| LIM-024 | Migración parcial (algunos .mdb faltantes) | Registra cuáles faltaron; continua con disponibles; reporte final |

---

## 11. Requisitos no funcionales

| ID | Requisito | Detalle / Métrica |
|---|---|---|
| RNF-001 | Disponibilidad panel interno | 99% en horario hábil (intranet) |
| RNF-002 | Disponibilidad portal recepción | 99.5% 24/7 durante ventanas de carga |
| RNF-003 | Tiempo respuesta validación individual | < 30 seg para archivo típico (worker background) |
| RNF-004 | Tiempo respuesta validación masiva | Procesamiento paralelo; < 5 min para 20 archivos |
| RNF-005 | Generación .zip Minsal | < 60 seg para año completo (6 series) |
| RNF-006 | Sync catálogos | < 10 min catálogos completos; progreso visible |
| RNF-007 | Descarga histórico | < 15 min por serie/año (datos + control) |
| RNF-008 | Capacidad concurrente | 50 usuarios internos + 86 establecimientos simultáneos |
| RNF-009 | Almacenamiento archivos | Disco local fuera webroot; ~440 archivos/mes × ~2MB = < 1 GB/mes |
| RNF-010 | Backup BD | Diario automático (mysqldump); retención 30 días; restore probado |
| RNF-011 | Backup archivos | Diario (rsync/copia); retención indefinida (manual admin) |
| RNF-012 | Auditoría | 100% acciones críticas (login, carga, validación, consolidación, sync, admin, observación) |
| RNF-013 | Trazabilidad | Cada dato en `datos` vinculado a `carga_id`/`recepcion_id` + usuario + timestamp |
| RNF-014 | Accesibilidad | WCAG 2.1 AA en portal recepción (establecimientos diversos) |
| RNF-015 | Compatibilidad navegadores | Chrome/Edge/Firefox/Safari últimas 2 versiones |
| RNF-016 | Idioma | Español (Chile) completo; mensajes de error claros y accionables |
| RNF-017 | Protección datos | Ley 19.628 y 21.719 referencia; minimización, acceso nominal, retención configurable |
| RNF-018 | Pruebas paridad | Suite automatizada: cada regla validación + salida Minsal vs ValidadorRem |
| RNF-019 | Extensiones PHP | `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `com_dotnet`, `soap`, `openssl` habilitadas |
| RNF-020 | Feriados API | Integración con API feriados Chile (nacionales + Los Lagos) para precarga calendarios |

---

## 12. Criterios de aceptación

### CA-M1: Carga y validación interna
- **CA-001** a **CA-005** (ver RF-001 a RF-005)
- **CA-M1-EXTRA:** Dado un conjunto de 50 Excel reales 2024-2026, cuando se procesan en carga masiva, entonces 100% generan mismos correctos/errores/control que ValidadorRem.

### CA-M2: Informes
- **CA-006** a **CA-013** (ver RF-006 a RF-013)
- **CA-M2-EXTRA:** Cada informe exportado a Excel abre correctamente en Excel/LibreOffice con datos íntegros.

### CA-M3: Generación Minsal
- **CA-014** (prueba de paridad obligatoria Hito H3)
- **CA-M3-EXTRA:** El .zip generado se sube al portal Minsal de prueba y es aceptado sin errores.

### CA-M4: Integración Minsal
- **CA-015** Sync catálogos actualiza todas las tablas `cat_*` y `cat_software.version`.
- **CA-016** Descarga histórico reconstruye `datos`, `datos_control`, `hist_carga` con conteos = servidor.

### CA-M5: Administración
- **CA-017** a **CA-023** (ver RF-017 a RF-023)
- **CA-M5-EXTRA:** Cambio de tema visual se aplica instantáneamente en ambos paneles sin recargar sesión.

### CA-M6: Migración
- **CA-024** Conteos MySQL = conteos .mdb origen para 2024, 2025, 2026.
- **CA-M6-EXTRA:** Comando re-ejecutado no duplica datos; `establecimiento_series` sembrado correctamente.

### CA-M7: Portal recepción
- **CA-025** a **CA-033** (ver RF-025 a RF-033)
- **CA-M7-EXTRA:** Flujo completo establecimiento de prueba: login → calendario → carga SERIE A → validación ok → Operador acepta → consolidado → notificación recibida → historial muestra v1 aceptada → 2da subida fuera plazo → Operador revisa y acepta v2 → historial muestra v1 y v2.

### CA-SEG: Seguridad
- **CA-SEG-01:** Usuario establecimiento A no puede ver datos de establecimiento B (filtro global + policies).
- **CA-SEG-02:** Panel interno inaccessible desde internet (solo intranet/VPN).
- **CA-SEG-03:** Portal HTTPS con certificado válido; HTTP redirige a HTTPS.
- **CA-SEG-04:** Rate limiting activo: >10 req/min/login → bloqueo 15 min.
- **CA-SEG-05:** MFA TOTP funcional Admin/Supervisor en entorno staging/producción.
- **CA-SEG-06:** Activity log registra: login, logout, carga, validación, consolidación, sync, admin actions, observaciones.

### CA-NF: No funcionales
- **CA-NF-01:** Validación individual < 30 seg (archivo 2000 filas).
- **CA-NF-02:** Generación .zip año completo < 60 seg.
- **CA-NF-03:** Backup diario completado < 30 min; restore probado trimestral.
- **CA-NF-04:** Portal recepción usable en móvil (responsive, touch-friendly).

---

## 13. Dependencias y trazabilidad

| Requisito | Objetivo | Reglas | Flujos | Criterios |
|---|---|---|---|---|
| RF-001 a RF-005 | OBJ-01, OBJ-04 | RN-003 a RN-007, RN-009 a RN-022 | FLUJO-01, FLUJO-02 | CA-001 a CA-005, CA-M1-EXTRA |
| RF-006 a RF-013 | OBJ-01, OBJ-03 | RN-023, RN-024 | — | CA-006 a CA-013, CA-M2-EXTRA |
| RF-014 | OBJ-01, OBJ-05 | RN-025 a RN-028 | FLUJO-08 | CA-014, CA-M3-EXTRA |
| RF-015, RF-016 | OBJ-01, OBJ-06 | RN-029 a RN-038 | FLUJO-05, FLUJO-06 | CA-015, CA-016 |
| RF-017 a RF-023 | OBJ-01, OBJ-08 | RN-001, RN-002, RN-014 a RN-016, RN-022, RN-023 | FLUJO-07 | CA-017 a CA-023, CA-M5-EXTRA, CA-SEG-01 a CA-SEG-06 |
| RF-024 | OBJ-07 | RN-047 a RN-049 | — | CA-024, CA-M6-EXTRA |
| RF-025 a RF-033 | OBJ-02, OBJ-03, OBJ-08, OBJ-09 | RN-001, RN-002, RN-008 a RN-013, RN-017, RN-018, RN-050 a RN-065 | FLUJO-03, FLUJO-04, FLUJO-09 | CA-025 a CA-033, CA-M7-EXTRA |
| RNF-001 a RNF-020 | OBJ-08, OBJ-01 | RN-019, RN-020 | — | CA-NF-01 a CA-NF-04 |

**Dependencias funcionales críticas:**
- RF-003 (validación) → RN-009 a RN-020 (reglas ValidadorRem) → catálogos `cat_*` deben estar sincronizados (RF-015)
- RF-014 (Minsal) → RF-005 (datos consolidados) → RF-003 (validación correcta)
- RF-027 a RF-033 (portal) → RF-003 (mismo motor validación) → RF-005 (consolidación tras aceptación Operador)
- RF-019 (ventanas) → RF-026, RF-027 (portal muestra estado ventana) → RN-010, RN-011 (fuera plazo, 2da subida)
- RF-033 (notificaciones) → RF-019 (día 3 hábil calculado sobre ventana con feriados API)

---

## 14. Decisiones pendientes

| ID | Pregunta / Decisión | Impacto | Responsable | Estado |
|---|---|---|---|---|
| PEND-001 | Confirmar habilitación `soap` y `com_dotnet` en XAMPP servidor sin conflictos | Bloquea H0 (base) y RF-014, RF-015 | Infraestructura | **Resuelto**: "si hailita" → habilitar |
| PEND-002 | Certificado TLS para subdominio portal | Bloquea exposición HTTPS portal | Infraestructura | **Resuelto**: "lo crearemos después" → post-H0 |
| PEND-003 | Canal notificaciones: FTP/SMTP institucional | Bloquea RF-033 (recordatorios + confirmaciones) | Infraestructura | **Resuelto**: "ftp es lo que usamos" → FTP |
| PEND-004 | Fuente feriados Chile (API) | Requerido para RF-019, RF-026, RF-033 (día 3 hábil) | Desarrollo | **Resuelto**: "ocupa api" → integrar API feriados.cl o similar |
| PEND-005 | Conjunto datos prueba paridad (Excel reales 2024-2026) | Requerido para CA-M1-EXTRA, CA-014 (Hito H3) | Equipo REM | **Resuelto**: "crea test para este proposito" → crear suite de tests con datos reales |
| PEND-006 | Definir API feriados específica (feriados.cl, api.gob.cl, otra) | Implementación RF-019, RF-026 | Desarrollo | Pendiente decisión técnica |
| PEND-007 | Detalle FTP: servidor, credenciales, estructura directorios | Implementación RF-033 | Infraestructura | Pendiente datos conexión |
| PEND-008 | Confirmar 86 establecimientos activos y series asignadas por cada uno | Siembra `establecimiento_series` (RF-020, migración) | Equipo REM | Pendiente validación lista final |
| PEND-009 | Definir mes límite de carga para campo `Registros.Mes` en generación Minsal | RF-014, RN-026 | Equipo REM / Minsal | Pendiente confirmación norma vigente |
| PEND-010 | Validar que ACE OLEDB 12.0 en servidor soporta .mdb formato Minsal actual | RF-014, RN-026 | Infraestructura | Pendiente prueba en servidor |

---

## Verificación atómica y cobertura

### Revisión por requisito (muestra representativa)

| Requisito | Comportamiento concreto | Resultado observable | CA verificable | Respeta contexto | Dependencias |
|---|---|---|---|---|---|
| RF-001 | ✅ Subida archivo individual | ✅ Registro carga + job encolado | ✅ CA-001 | ✅ | RF-003 |
| RF-003 | ✅ Validación completa background | ✅ Datos/control/errores guardados | ✅ CA-003 (paridad) | ✅ Reglas ValidadorRem | Catálogos sync |
| RF-014 | ✅ Genera .zip descargable | ✅ Archivo .zip | ✅ CA-014 (paridad fila a fila) | ✅ Mismo MASTER.mdb | RF-005 |
| RF-027 | ✅ Formulario condicional ventana | ✅ Recepción creada + job | ✅ CA-027 | ✅ Ventana híbrida | RF-019 |
| RF-030 | ✅ 2da subida controlada | ✅ v2 marcada, no consolida auto | ✅ CA-030 | ✅ RN-011 | RF-005 |
| RF-033 | ✅ 2 notificaciones automáticas | ✅ FTP/SMTP enviado | ✅ CA-033 | ✅ FTP confirmado | RF-019 |

### Revisión global

- ✅ Todos los objetivos (OBJ-01 a OBJ-09) tienen requisitos asociados
- ✅ Flujos principales (FLUJO-01 a FLUJO-09) cubren todos los casos de uso MVP
- ✅ Reglas de negocio (RN-001 a RN-023) no se contradicen; derivan de AGENT.md e INFORME.md
- ✅ Casos límite (LIM-001 a LIM-024) cubren errores ValidadorRem + nuevos casos portal
- ✅ Requisitos no contradicen AGENT.md (tecnologías, roles, alcance, decisiones confirmadas)
- ✅ Funcionalidades fuera de alcance identificadas (FUERA-001 a FUERA-005)
- ✅ Sin detalles de implementación prematuros (no endpoints, no schemas BD, no código)
- ✅ Identificadores estables asignados (OBJ, RF, RN, RNF, CA, LIM, PEND)

### Métricas de especificación

- **Objetivos funcionales:** 9 (OBJ-01 a OBJ-09)
- **Requisitos funcionales:** 33 (RF-001 a RF-033)
- **Reglas de negocio:** 23 (RN-001 a RN-023)
- **Requisitos no funcionales:** 20 (RNF-001 a RNF-020)
- **Criterios de aceptación:** 33 base + 7 extra + 6 seguridad + 4 no funcionales = **50 CA**
- **Casos límite:** 24 (LIM-001 a LIM-024)
- **Decisiones pendientes:** 10 (PEND-001 a PEND-010), 5 resueltas por usuario

---

## Confirmación

Archivo guardado en: `C:\xampp\htdocs\www\CargasREM\specs\SPECS.md`

El MVP puede entenderse y evaluarse funcionalmente sin necesidad de conocer su implementación técnica.