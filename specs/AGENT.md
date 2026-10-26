# WebCargaREM — Contexto General del Proyecto

## 1. Nombre e identificación del proyecto

**Nombre:** Plataforma Web de Gestión y Recepción REM  
**Nombre técnico:** WebCargaREM  
**Servicio objetivo:** Servicio de Salud Osorno (`idServicio = 23`)  
**Estado:** Idea cerrada y validada — lista para diseño técnico e implementación  
**Fecha de levantamiento:** 23 de octubre de 2026

---

## 2. Descripción general

WebCargaREM es la versión web del sistema **ValidadorRem** (aplicación de escritorio VB.NET/WinForms/Access) que el Servicio de Salud Osorno usa para validar, consolidar y empaquetar los archivos Excel REM (Registro Estadístico Mensual) que envían 86 establecimientos mensualmente para su carga al portal del Minsal (`cargasrem.minsal.cl`).

El proyecto reemplaza los formularios WinForms y bases Access por **dos portales web** sobre la infraestructura existente (XAMPP en servidor Windows del servicio):

1. **Panel interno (back-office, intranet):** para el equipo REM — carga, validación, informes, generación del archivo Minsal, administración de catálogos, ventanas de carga y usuarios.
2. **Portal de Recepción de Información Mensual (HTTPS, subdominio institucional):** los 86 establecimientos inician sesión y suben sus series REM directamente, reemplazando el envío por correo. El administrador controla la ventana de carga (por defecto días 1–5 hábiles con feriados de Chile editables) y puede abrir/cerrar/prorrogar manualmente.

---

## 3. Problema o necesidad que se busca resolver

| Problema actual (ValidadorRem) | Necesidad a resolver |
|---|---|
| Aplicación de escritorio, instalación por puesto | Operar desde navegador (cero instalación) |
| Un solo usuario, sin trazabilidad | Multiusuario con roles y auditoría completa |
| Recepción por correo electrónico (sin control de fechas, sin comprobante) | Portal trazable con cuentas por establecimiento, ventana de carga controlada, validación inmediata y comprobante |
| SQL por concatenación, sin transacciones, bases Access que crecen sin control | Consultas parametrizadas, transacciones, MySQL, backups automáticos |
| Catálogos desactualizados → rechazos de archivos válidos | Sincronización automática desde servicios Minsal |
| Generación .mdb manual, propensa a error | Misma salida .mdb/.zip idéntica, generada en el mismo servidor Windows |

---

## 4. Idea general de la solución

- **Paridad funcional total** con ValidadorRem: los 6 módulos originales tienen equivalente web, más el portal de recepción (nuevo).
- **Mismo motor de validación:** las reglas siguen siendo **datos, no código** (catálogos `Diccionario`, `Thojas`, `Excep`, `colexcep`, `version` en MySQL). Cambiar una regla = actualizar una fila.
- **Misma salida Minsal:** genera el `.zip` (GZip sobre `.mdb` plantilla `MASTER.mdb` vía COM/ADODB + ACE OLEDB 12.0) idéntico al actual.
- **Arquitectura base:** Laravel 12 + Filament 4 (dos paneles) + MySQL + PhpSpreadsheet + colas `database` + workers como tareas programadas de Windows.
- **Migración histórica 2024–2026** desde los `.mdb` existentes (comando idempotente).

---

## 5. Usuarios o público objetivo

| Rol | Acceso | Qué hace |
|---|---|---|
| **Operador** | Panel interno (intranet) | Carga archivos (individual/masiva), valida, revisa envíos de establecimientos, acepta/observa |
| **Supervisor** | Panel interno (intranet) | Informes, exportaciones, revisión, dashboard de cumplimiento |
| **Admin** | Panel interno (intranet) | Todo + gestión de usuarios (interno + 2 cuentas/establecimiento), ventanas de carga, catálogos, servicio, tema visual, auditoría |
| **Usuario Establecimiento** (titular + suplente, 2 cuentas nominales por estab.) | Portal recepción (HTTPS) | Ve calendario, sube series que le corresponden, ve validación inmediata, reenvía si hay observaciones, ve su historial y cumplimiento |

---

## 6. Objetivo general

Entregar una plataforma web operativa que:

1. Elimine los riesgos técnicos del sistema actual (concatenación SQL, sin transacciones, sin auditoría, bases Access frágiles).
2. Reemplace el correo como canal de recepción por un portal trazable, con validación inmediata y control de cumplimiento.
3. Preserve **exactamente** las reglas de validación y el formato de salida al Minsal (.mdb/.zip).
4. Permita operar desde el navegador (intranet para el equipo, HTTPS para establecimientos).
5. Quede lista para escalar a otros servicios (todas las tablas llevan `idservicio`; día 1 opera solo Osorno).

---

## 7. Alcance conceptual inicial

**Incluido (MVP):**
- Módulos equivalentes a los 6 originales: carga/validación (individual y masiva), informes (matriz, control, log errores, comparativos, duplicidad, auditorías, export), generación archivo Minsal, integración catálogos/histórico Minsal, administración (usuarios, ventanas, catálogos, servicio, tema).
- Portal de recepción: cuentas por establecimiento (titular + suplente), ventana de carga híbrida (calendario + manual), validación inmediata, versionado de reenvíos, observaciones, panel de cumplimiento, notificaciones automáticas (recordatorio día 3 hábil + confirmación post-carga).
- Migración histórica 2024–2026 desde `.mdb` (copias, originales intocados).
- Seguridad: MFA obligatorio en producción (Admin/Supervisor), aislamiento por establecimiento, panel interno solo intranet, portal HTTPS con rate limiting.

**Fuera de alcance en esta fase:**
- App móvil nativa
- Firma electrónica de envíos
- Conexión automática con sistemas clínicos de establecimientos

---

## 8. Tecnologías o restricciones generales ya definidas

| Capa | Tecnología | Restricción / Nota |
|---|---|---|
| Servidor | **Windows + XAMPP (Apache)** | Infraestructura existente; **requisito** para COM/ADODB + ACE 12.0 (genera .mdb) |
| Lenguaje | **PHP 8.3+** | Requisito Laravel 12 / Filament 4 |
| Framework | **Laravel 12** | PHP 8.2–8.5, ecosistema maduro |
| Paneles | **Filament 4 (dos paneles)** | Panel interno + Portal establecimientos |
| Base de datos | **MySQL / MariaDB** (XAMPP) | Ya instalada; consultas parametrizadas nativas |
| Lectura Excel | **PhpSpreadsheet** | Lee `.xls`, `.xlsx`, `.xlsm`; `setReadDataOnly` + valores cacheados |
| Colas | **Laravel Queues** (`database`) + worker tarea programada Windows | Validación, consolidación, notificaciones en background |
| Integración Minsal | **HTTP GET + XML** (Guzzle + SimpleXML) — primaria<br>**SOAP** (`SoapClient`) — respaldo<br>Importador `Global.mdb` — último recurso | Endpoints vivos, WSDL operativo, GET expuesto (verificado) |
| Generación .mdb | **PHP COM / ADODB + ACE OLEDB 12.0** | Proveedor ACE ya instalado (lo usa ValidadorRem) |
| Compresión | `ZipArchive` / GZip (PHP nativo) | Réplica .zip GZip actual |
| Export Excel | **Laravel Excel (maatwebsite)** | Informes, logs, cumplimiento |
| Roles/permisos | **Filament Shield** | 4 roles, permisos por recurso y establecimiento |
| Auditoría | **spatie/laravel-activitylog** | Quién cargó, validó, observó, aceptó, borró, sincronizó |
| Notificaciones | **Laravel Mail** (SMTP institucional) | Recordatorio día 3 hábil + confirmación post-carga |
| Archivos subidos | **Disco local fuera del webroot** | Retención indefinida (borrado manual admin), hash SHA-256 |
| Pruebas | PHPUnit/Pest + **pruebas de paridad** contra ValidadorRem | Cada regla y salida Minsal verificadas |

**Extensiones PHP requeridas en XAMPP:** `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `com_dotnet`, `soap`, `openssl` (verificar en H0 — `soap` y `com_dotnet` pendientes de habilitar en `php.ini`).

---

## 9. Principios y consideraciones importantes del proyecto

1. **Catálogo como datos, no como código** — Las reglas de posicionamiento, excepciones y versiones viven en tablas MySQL. No hay lógica de validación hardcodeada por serie/hoja.
2. **Una tabla `datos` unificada** con columna `serie` — En lugar de un `.mdb` por serie×año. Simplifica consultas globales y elimina 12+ archivos operativos.
3. **Validación en cola** — El request HTTP solo guarda el archivo y encola el job; el worker valida y deja resultado listo para revisión. UI no se bloquea.
4. **Dos paneles Filament, un solo núcleo** — Comparten dominio, BD y reglas; se separan por rutas, permisos y exposición de red. **Aislamiento por `establecimiento_id`**: un establecimiento nunca ve datos de otro.
5. **Generación .mdb solo en Windows** — Restricción de plataforma resuelta por el hosting elegido (XAMPP en servidor Windows del servicio).
6. **Ventana de carga híbrida** — Calendario automático (días 1–5 hábiles + feriados Chile precargados y editables) + control manual del admin (abrir/cerrar/prorrogar con auditoría).
7. **Envíos fuera de plazo se aceptan** — Marcados "fuera de plazo" (error de cumplimiento). Se permite **una 2da subida** (versión 2, flag `es_segunda_subida`), no consolida sin revisión del equipo REM.
8. **Notificaciones automáticas** — (a) Recordatorio día 3 hábil a pendientes; (b) Confirmación post-carga con fecha, versión, estado y resumen de validación.
9. **MFA obligatorio en producción** para Admin y Supervisor (TOTP); opcional en desarrollo.
10. **Transición 1–2 meses en paralelo** — ValidadorRem sigue siendo fuente oficial ante Minsal hasta corte (criterio: 2 ciclos mensuales sin divergencias + al menos un .zip aceptado por el portal).
11. **Migración histórica 2024–2026** — Desde copias de `.mdb` en directorio temporal (originales intocados). Comando idempotente. ~2,3M filas estimadas.
12. **Seguridad por capas:** Panel interno solo intranet/VPN. Portal en subdominio con HTTPS obligatorio, rate limiting, bloqueo por intentos, WAF si la infraestructura lo permite. Cuentas nominales verificadas por admin.

---

## 10. Decisiones pendientes y limitaciones conocidas

### Decisiones confirmadas (no reabrir)
- Conectividad → Subdominio institucional HTTPS
- Ventana de carga → Días 1–5 hábiles + feriados Chile editables + control manual admin
- Fuera de plazo → Aceptar + marcar + 2da subida (v2)
- Recordatorios → 2 automáticos (día 3 hábil + confirmación post-carga)
- MFA → Obligatorio solo en producción (Admin + Supervisor)
- Cuentas establecimiento → Titular + suplente (2 nominales)
- Tema visual → Personalizado Osorno (colores, logo, dark/light)
- Retención Excel → Indefinida (borrado manual admin)
- Estructura menús → 5 grupos: Carga, Informes, Minsal, Administración, Recepción

### Limitaciones conocidas
- Generación .mdb requiere Windows + ACE OLEDB 12.0 (ya instalado en servidor actual).
- Servicios Minsal SOAP legacy (2015) sin firma de datos; .zip no cifrado (solo GZip).
- XAMPP expuesto solo para portal; panel interno debe permanecer en intranet/VPN.
- Volumen estimado: 86 establecimientos, ~440 archivos/mes, ~2,3M filas a 3 años — manejable en MySQL sin particionado inicial.

### Preguntas abiertas / pendientes de validar en H0
1. ¿Confirmar que `soap` y `com_dotnet` se pueden habilitar en el XAMPP del servidor sin conflictos?
2. ¿Certificado TLS institucional disponible para el subdominio del portal?
3. ¿SMTP institucional configurado y accesible desde el servidor para notificaciones?
4. ¿Listado oficial de feriados Chile (nacionales + regionales Los Lagos) para precargar calendarios 2025–2027?
5. ¿Validación de paridad: se dispone de conjunto de datos de prueba representativo (Excel reales 2024–2026) para comparar salidas ValidadorRem vs WebCargaREM?

---

## Referencias de origen

- **Prompt maestro (conversación):** Contexto consolidado, decisiones del levantamiento 23-10-2026, arquitectura, modelo de datos, módulos, reglas, flujos, roadmap.
- **INFORME.md:** Análisis estático de `ValidadorRem.exe` — decompilación ILSpy + índice codebase-memory-mcp (1.971 nodos / 8.799 aristas) + verificación esquema real vía ACE OLEDB 12.0. Documenta el sistema actual módulo a módulo, flujo de lectura Excel, guardado en .mdb, mapa completo de bases, generación archivo Minsal, servicios web, riesgos detectados.

---

*Este documento es la referencia de contexto para agentes y desarrolladores. No contiene especificaciones funcionales detalladas, arquitectura de código, estructura de archivos, clases ni decisiones de implementación — esas viven en specs técnicos separados (`specs/SPECS.md`, `specs/PLAN.md`, `specs/TASK.md`).*