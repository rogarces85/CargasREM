# WebCargaREM — Plataforma Web de Gestión y Recepción REM

**Servicio de Salud Osorno** (`idServicio = 23`)

**Fecha del levantamiento:** 23 de octubre de 2026
**Base documental:** `INFORME.md` (análisis estático del `ValidadorRem.exe`), levantamiento interactivo de requerimientos, pruebas de conectividad reales, y ampliación del mismo día con el **Sistema de Recepción de Información Mensual**.
**Estado:** idea cerrada y validada — lista para pasar a diseño técnico e implementación.

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [Objetivo](#2-objetivo)
3. [Decisiones del levantamiento](#3-decisiones-del-levantamiento)
4. [Evidencia recogida (pruebas reales)](#4-evidencia-recogida-pruebas-reales)
5. [Stack tecnológico propuesto](#5-stack-tecnológico-propuesto)
6. [Arquitectura del sistema](#6-arquitectura-del-sistema)
7. [Modelo de datos (MySQL)](#7-modelo-de-datos-mysql)
8. [Módulos del sistema](#8-módulos-del-sistema)
9. [Reglas de validación a preservar](#9-reglas-de-validación-a-preservar)
10. [Flujos de carga y recepción](#10-flujos-de-carga-y-recepción)
11. [Generación del archivo Minsal](#11-generación-del-archivo-minsal)
12. [Integración con los servicios del Minsal](#12-integración-con-los-servicios-del-minsal)
13. [Migración histórica 2024–2026](#13-migración-histórica-20242026)
14. [Seguridad y auditoría](#14-seguridad-y-auditoría)
15. [Roadmap por hitos](#15-roadmap-por-hitos)
16. [Transición con ValidadorRem](#16-transición-con-validadorrem)
17. [Riesgos y mitigaciones](#17-riesgos-y-mitigaciones)
18. [Decisiones abiertas](#18-decisiones-abiertas)

---

## 1. Resumen ejecutivo

WebCargaREM es la versión web del **ValidadorRem**: la aplicación de escritorio con la que el Servicio de Salud Osorno valida, consolida y empaqueta los archivos Excel REM que envían los establecimientos mensualmente para su carga al portal del Minsal.

La propuesta reemplaza los formularios WinForms y las bases Access por **dos portales** sobre la infraestructura que el servicio ya tiene (XAMPP en intranet):

1. **Panel interno (back-office)** para el equipo REM: carga, validación, informes, generación del archivo Minsal y administración.
2. **Sistema de Recepción de Información Mensual (portal de establecimientos)**: los 86 establecimientos del servicio inician sesión y **suben sus series REM directamente**, reemplazando el envío por correo electrónico. El administrador controla la **ventana de carga** (por defecto los 5 días hábiles del mes) y puede **habilitar o deshabilitar el formulario manualmente**. Cada envío queda con **fecha, estado y trazabilidad**, y el establecimiento recibe **validación inmediata** para corregir antes de que el equipo REM revise.

Se conservan **exactamente las mismas reglas de validación** y la **misma salida para el portal Minsal** (.mdb comprimido), porque ambas son exigencias externas que no pueden cambiar.

La ventaja principal del sistema original —un catálogo central que define dónde está cada dato (`Diccionario`, `Thojas`, `Excep`)— **se conserva como principio de diseño**: las reglas siguen siendo datos, no código.

El levantamiento fue validado con evidencia real: los servicios del Minsal respondieron, los datos de Osorno están disponibles y el volumen (86 establecimientos, ~440 archivos/mes) es holgadamente manejable.

---

## 2. Objetivo

| Objetivo | Cómo se logra |
|---|---|
| **Paridad funcional** con ValidadorRem | Los 6 módulos del original tienen equivalente web (ver §8) |
| **Eliminar los riesgos del informe** | Transacciones, consultas parametrizadas, backups automáticos, auditoría de acciones |
| **Multiusuario interno** | Login propio, roles internos, registro de quién hace qué |
| **Reemplazar el correo como canal de recepción** | Portal con cuentas por establecimiento, carga guiada y comprobante de envío |
| **Controlar cumplimiento y fechas de carga** | Ventanas de carga + panel de cumplimiento con fecha y estado por establecimiento/serie |
| **Habilitar/deshabilitar la recepción** | El administrador abre, cierra o prorroga la ventana (por calendario o manualmente) |
| **Operación desde el navegador** | Cero instalación por puesto (equipo REM y establecimientos) |
| **Preservar la salida al Minsal** | Idéntico .mdb/.zip generado en el mismo servidor Windows |
| **Escalar a otros servicios** | Todas las tablas con `idservicio`; el día 1 opera solo Osorno, sin rediseño posterior |

**Fuera de alcance en esta fase:** app móvil nativa, firma electrónica de los envíos y conexión automática con sistemas clínicos de los establecimientos.

---

## 3. Decisiones del levantamiento

| # | Pregunta | Decisión tomada |
|---|---|---|
| 1 | ¿Para quién? | **Un servicio (Osorno)**, con modelo de datos preparado para múltiples servicios |
| 2 | ¿Dónde corre? | **XAMPP** en el servidor del servicio (panel interno en intranet; portal de establecimientos con acceso controlado — ver §6.3) |
| 3 | ¿Stack? | **PHP + MySQL** |
| 4 | ¿Quién carga? | **Equipo REM (carga interna) + establecimientos (portal)** — el portal reemplaza el correo; el equipo conserva la carga asistida |
| 5 | ¿Alcance MVP? | **Módulos completos**: carga+validación+dashboard, archivo Minsal, histórico desde servidor, actualización de catálogos y portal de recepción |
| 6 | ¿Formato Minsal? | **Mantener el mismo** .mdb/.zip que acepta hoy el portal |
| 7 | ¿Histórico a migrar? | **2024–2026** |
| 8 | ¿Roles? | **4 roles**: Operador, Supervisor, Admin y **Usuario Establecimiento**, con auditoría |
| 9 | ¿Flujo de carga? | **Validar y confirmar**: nada se consolida sin visto bueno del equipo REM |
| 10 | ¿Informes? | **Todos**: matriz estab×mes, control faltante, log de errores, comparativos, duplicidad, auditorías de datos, export a Excel + **panel de cumplimiento** |
| 11 | ¿Interfaz? | **Filament** (Laravel), con dos paneles (interno y establecimientos) |
| 12 | ¿Integración Minsal? | **Probar y decidir** → probado: HTTP GET y SOAP funcionan (ver §4) |
| 13 | ¿Autenticación? | **Base de datos propia** (usuarios y claves en MySQL) |
| 14 | ¿Volumen? | "No sé" → **medido**: 86 establecimientos, 73 activos en SERIE A 2026 |
| 15 | ¿Convivencia? | **Transición 1–2 meses** en paralelo, luego corte |
| 16 | ¿Plazo? | **Sin fecha fija**; entrega por hitos verificables |
| 17 | ¿Nombre? | **WebCargaREM** (con el portal de recepción como componente) |
| 18 | ¿Portal de establecimientos? | **Sí** — cada establecimiento sube sus series REM desde el navegador |
| 19 | ¿Quién habilita la carga? | **Administrador**: ventana por calendario (5 días hábiles) + abrir/cerrar manual |
| 20 | ¿Nuevo rol? | **Usuario Establecimiento**: solo ve y sube lo de su establecimiento |
| 21 | ¿Validación inmediata al establecimiento? | **Sí** — recibe sus errores al subir y corrige antes de la revisión del equipo REM |
| 22 | ¿Reenvíos? | **Sí, versionados**, con observaciones y trazabilidad |
| 23 | ¿Conectividad de establecimientos? | **Subdominio institucional con HTTPS** — portal público-controlado, panel interno en intranet (ver §6.3) |
| 24 | ¿Ventana de carga? | **Híbrida**: calendario automático (días 1–5 hábiles **con feriados de Chile precargados y editables**) + control manual del administrador (abrir, cerrar, prorrogar) |
| 25 | ¿Envíos fuera de plazo? | **Se aceptan**, se marcan "fuera de plazo" (error de cumplimiento); **se permite 2da subida** (versión 2, marcada), no consolida sin revisión del equipo REM |
| 26 | ¿Recordatorios por correo? | **Sí, dos automáticos**: (a) pre-vencimiento día 3 hábil a pendientes; (b) confirmación post-carga con fecha, versión, estado y resumen de validación |
| 27 | ¿MFA? | **Obligatorio solo en producción** (Admin + Supervisor); opcional en desarrollo/pruebas |
| 28 | ¿Cuentas por establecimiento? | **Titular + suplente** (dos cuentas nominales por establecimiento, ambas con acceso completo al portal) |
| 29 | ¿Tema visual? | **Personalizado (Osorno)**: colores institucionales, logo del servicio, dark/light configurable |
| 30 | ¿Retención Excel? | **Indefinido (borrado manual por admin)**; sin borrado automático programado |
| 31 | ¿Estructura menús? | **5 grupos funcionales**: Carga, Informes, Minsal, Administración, Recepción |
| 32 | ¿Verificar XAMPP? | **Verificar ahora** (PHP ≥ 8.3 + extensiones: pdo_mysql, mbstring, xml, curl, zip, gd, com_dotnet, soap, openssl) |

---

## 4. Evidencia recogida (pruebas reales)

Todas las pruebas fueron de **solo lectura** (GET a servicios públicos y consultas sobre copias de las bases en un directorio temporal).

### 4.1 Servicios del Minsal: vivos y operativos

| Servicio | Prueba | Resultado |
|---|---|---|
| `webservicedb.asmx` | WSDL | ✅ Operaciones confirmadas: `datos`, `servicio`, `diccionario`, `Thoja`, `Version`, `Excep`, `ColExcep` |
| `CargaSerieServ.asmx` | WSDL | ✅ `DatosSerie(serie: string, servicio: int, ano: int)` — descarga del histórico de datos |
| `WebService1.asmx` | GET `/version` | ✅ Responde `2.00.417` (versión de la BD central) |
| `webservicedb.asmx/servicio` | GET | ✅ Devuelve el **DataSet real** con los 29 servicios del país |

**Hallazgo clave:** los servicios exponen además bindings **HTTP GET** (`/servicio`, `/datos`, `/version`, …). PHP puede consumirlos con un cliente HTTP + parser XML, **sin necesidad de la extensión SOAP**. Esto simplifica la implementación y reduce dependencias.

**Dato operacional:** la BD local está en `2.00.416` y la del servidor en `2.00.417` → el sistema web debe sincronizar catálogos al primer arranque (el escritorio actual lo advierte al iniciar).

### 4.2 Datos de Osorno (volumen real)

| Métrica | Valor |
|---|---|
| Establecimientos del servicio | **86** (86 activos) |
| Establecimientos con datos en SERIE A 2026 | **73** |
| Filas en SERIE A 2026 | **125.844** |
| Cobertura del catálogo `Thojas` | **2023, 2024, 2025, 2026** (4 años) |
| Versión BD local | `2.00.416` |

**Implicancia de diseño:** 6 series × ~73 archivos ≈ **440 archivos/mes** en el peor caso. La cola de trabajos con driver `database` de Laravel es más que suficiente. A 3 años de histórico: ~2,3 millones de filas — trivial para MySQL.

**Línea base de cumplimiento:** 73 de 86 establecimientos reportaron SERIE A 2026 (**85%**). El portal de recepción hará visible y accionable el 15% restante, con fecha y estado por establecimiento y serie — hoy esa información solo se puede deducir revisando los correos.

### 4.3 Versiones verificadas contra documentación oficial

| Componente | Requisito |
|---|---|
| Filament 4 | PHP 8.2+, Laravel 11.28+, Livewire 3.8.7+, Tailwind 4.1+ |
| Laravel 12 | PHP 8.2–8.5 |
| Laravel 13 | PHP 8.3+ |

---

## 5. Stack tecnológico propuesto

### 5.1 Stack por capa

| Capa | Tecnología | Justificación |
|---|---|---|
| Servidor | **Windows + XAMPP** (Apache) | Infraestructura existente; además es requisito para generar .mdb |
| Lenguaje | **PHP 8.3+** | Requisito de Laravel 12/13 y Filament 4 |
| Framework | **Laravel 12** | Rango amplio de PHP (8.2–8.5), ecosistema maduro |
| Paneles / dashboards | **Filament 4 (dos paneles)** | Tablas filtrables, formularios, login, roles y widgets listos; un panel interno y un portal de establecimientos |
| Base de datos | **MySQL / MariaDB** (XAMPP) | Ya instalada; consultas parametrizadas nativas |
| Lectura de Excel | **PhpSpreadsheet** | Lee `.xls`, `.xlsx` y `.xlsm`; con `setReadDataOnly`/valores cacheados resuelve las fórmulas |
| Colas de trabajo | **Laravel Queues** (`database`) + worker como tarea programada de Windows | Validación, consolidación y notificaciones en segundo plano |
| Integración Minsal | **HTTP GET + XML** (Guzzle + SimpleXML), con `SoapClient` como respaldo | Evidenciado en §4.1; menos dependencias |
| Generación .mdb | **PHP COM / ADODB + ACE OLEDB 12.0** | El proveedor ACE ya está instalado (lo usa ValidadorRem) |
| Compresión | **`ZipArchive` / GZip** (PHP nativo) | Replica el .zip GZip actual |
| Export a Excel | **Laravel Excel (maatwebsite)** | Exportaciones de informes, logs y cumplimiento |
| Roles/permisos | **Filament Shield** | 4 roles con permisos por recurso y por establecimiento |
| Auditoría | **spatie/laravel-activitylog** | Quién cargó, validó, observó, aceptó, borró o actualizó catálogos |
| Notificaciones (opcional) | **Laravel Mail** (SMTP institucional) | Recordatorios de carga y avisos de observaciones |
| Almacenamiento de archivos | **Disco local fuera del webroot** (o almacenamiento compatible S3 si se requiere) | Conservación y descarga de los Excel recibidos |
| Pruebas | PHPUnit/Pest + pruebas de paridad contra ValidadorRem | Cada regla de validación y la salida Minsal verificadas |

### 5.2 ¿Por qué PHP + Laravel + Filament y no otro stack?

| Criterio | PHP + Laravel + Filament | Node/Next.js | Python/Django | C#/.NET |
|---|---|---|---|---|
| Aprovecha XAMPP existente | ✅ directo | ❌ (Node aparte) | ⚠️ (Python aparte) | ❌ (IIS) |
| Velocidad de construcción de dashboards | ✅ Filament resuelve tablas/forms/roles | ⚠️ más código | ⚠️ admin básico | ⚠️ Razor/Blazor |
| Generar `.mdb` en Windows | ✅ COM/ADODB | ⚠️ difícil | ⚠️ difícil | ✅ nativo |
| Mantenimiento en la intranet | ✅ un solo runtime | ⚠️ dos runtimes | ⚠️ dos runtimes | ✅ un runtime |
| Familiaridad del equipo | ✅ (XAMPP) | ⚠️ | ⚠️ | ✅ (VB.NET original) |

C#/.NET es la alternativa conceptual más cercana al original, pero implicaría montar IIS y renunciar a Filament; la decisión del levantamiento (XAMPP) lo descarta.

---

## 6. Arquitectura del sistema

### 6.1 Vista de componentes

```
┌──────────── Portal de Establecimientos (HTTPS) ────────────┐   ┌──────── Panel interno (intranet) ────────┐
│  Usuario Establecimiento                                   │   │  Operador · Supervisor · Admin            │
│  login · calendario · carga de series · historial          │   │  carga · informes · Minsal · administración│
└──────────────────────────────┬─────────────────────────────┘   └──────────────────┬────────────────────────┘
                               │                                                    │
┌──────────────────────────────▼────────────────────────────────────────────────────▼───────────────────────┐
│                                  Laravel 12 (XAMPP / Apache)                                              │
│                                                                                                           │
│  ┌───────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌───────────────┐  ┌────────────────┐  │
│  │ M7 Recepción  │  │ M1 Carga   │  │ M2 Informes│  │ M3 Minsal  │  │ M4 Integración│  │ M5 Admin       │  │
│  │ portal estab. │  │ validación │  │ dashboard  │  │ .mdb/.zip  │  │ catálogos     │  │ ventanas/roles │  │
│  └───────┬───────┘  └─────┬──────┘  └─────┬──────┘  └─────┬──────┘  └───────┬───────┘  └───────┬────────┘  │
│          └────────────────┴───────────────┴───────────────┴─────────────────┴──────────────────┘           │
│                                  Dominio REM (servicios de aplicación)                                     │
│     validador · recepción · consolidación · cumplimiento · exportador · catálogos (data-driven)           │
│                                                                                                           │
│   ┌──────────────────────┐   ┌──────────────────────────────┐   ┌──────────────────────────────────────┐   │
│   │ MySQL (XAMPP)        │   │ Jobs en cola (database)       │   │ Almacenamiento de archivos           │   │
│   │ datos + catálogos    │   │ validar · notificar · exportar│   │ (fuera del webroot)                  │   │
│   └──────────────────────┘   └──────────────────────────────┘   └──────────────────────────────────────┘   │
└───────────────────────────────────────────────────────────────────────────────────────────────────────────┘
                                                     │
        ┌────────────────────────────────────────────┼─────────────────────────────────────────┐
        │                                            │                                         │
┌───────▼────────────┐                  ┌────────────▼───────────┐                 ┌───────────▼──────────┐
│ PhpSpreadsheet      │                  │ COM/ADODB + ACE 12.0   │                 │ HTTP GET / SOAP      │
│ lectura .xls/.xlsx/ │                  │ plantilla MASTER.mdb   │                 │ cargasrem.minsal.cl  │
│ .xlsm               │                  │ → .mdb → .zip GZip     │                 │ catálogos/histórico  │
└─────────────────────┘                  └────────────────────────┘                 └──────────────────────┘
```

### 6.2 Decisiones arquitectónicas clave

1. **Catálogo como datos, no como código.** Igual que el original: `cat_diccionario`, `cat_thojas`, `cat_excep`, `cat_colexcep`, `cat_versiones`. Cambiar una regla = actualizar una fila, no recompilar.
2. **Una tabla `datos` con columna `serie`** en lugar de un archivo .mdb por serie×año. Simplifica consultas globales y elimina los 12+ archivos operativos.
3. **Procesamiento validado en cola.** El request HTTP solo guarda el archivo y encola; el worker valida y deja el resultado listo para la revisión.
4. **Dos paneles Filament sobre el mismo núcleo.** El panel interno y el portal comparten dominio, base de datos y reglas; se separan por rutas, permisos y exposición de red. Un establecimiento **nunca** puede ver datos de otro (aislamiento por `id_establecimiento`).
5. **Generación .mdb en el mismo servidor Windows** (restricción de plataforma resuelta por el hosting elegido).
6. **Integración por HTTP GET** como vía principal, SOAP como respaldo, e **importador desde `Global.mdb`** como último recurso si el servidor del Minsal no responde.
7. **La recepción no bloquea la consolidación.** Un envío fuera de ventana se acepta marcado como "fuera de plazo": la estadística no se pierde y el atraso queda registrado.

### 6.3 Exposición de red: el punto que cambia con el portal

El panel interno vive en la intranet. **Los establecimientos, en cambio, están distribuidos en toda la provincia** y no están dentro de la red del servicio: necesitan llegar al portal desde sus propias conexiones.

**Decisión tomada: subdominio institucional con HTTPS** (opción A).

| Aspecto | Definición |
|---|---|
| Superficie | Solo las rutas del portal de recepción (`/portal/*`) |
| Acceso | HTTPS obligatorio (TLS 1.2+), certificado institucional |
| Protección | Rate limiting por IP, bloqueo por intentos fallidos, WAF si la infraestructura lo permite |
| Panel interno | Queda **exclusivo a la intranet/VPN** — nunca se expone públicamente |
| Autenticación | Cuentas nominales por establecimiento + MFA opcional para admin/supervisor (§18.5) |

Esta decisión se refleja en la arquitectura (§6.1) y en los controles de seguridad (§14).

---

## 7. Modelo de datos (MySQL)

### 7.1 Catálogos (sincronizados desde el Minsal o importados de Global.mdb)

| Tabla | Columnas |
|---|---|
| `servicios` | id, nombre, id_region, orden, eliminado |
| `establecimientos` | id, nombre, direccion, telefono, fax, idservicio, id_comuna, id_dependencia, id_tipo, eliminado, propiedad |
| `cat_diccionario` | codigo_prestacion, mes, ano, texto_prestacion, hoja, serie, posicion, tipo_dato, linea, inicio, fin |
| `cat_thojas` | serie, hoja, ano, mes, rango |
| `cat_excep` | codigo_prestacion, mes, ano, hoja, serie, posicion, tipo_dato, col_excep |
| `cat_colexcep` | codigo_pres, col |
| `cat_versiones` | mes, ano, version, serie, serie_excel |
| `cat_software` | nombre, version (versión de BD vigente) |

### 7.2 Operativas

| Tabla | Contenido | Notas |
|---|---|---|
| `cargas` | id, idservicio, ano, serie, mes, id_establecimiento, archivo, origen (`portal`/`interno`), estado (`pendiente`/`validado`/`guardado`/`rechazado`), usuario_id, timestamps | Trazabilidad del flujo validar→confirmar |
| `datos` | id, idservicio, **serie**, mes, ano, id_establecimiento, codigo_prestacion, fecha_ingreso, **col01…col50** | Índice `(serie, ano, id_establecimiento, mes)`; ~2,3M filas a 3 años |
| `datos_control` | id, idservicio, serie, mes, ano, id_establecimiento, **col01…col05** | Hojas de control/resumen |
| `hist_carga` | id_establecimiento, mes, ano, serie, idservicio, fuente (`upload`/`servidor`) | Equivale a `HistCarga` de Trans.mdb |
| `log_errores` | id, carga_id, id_establecimiento, mes, serie, linea, hoja, dato, ano, idservicio | Equivale a `LogError` |
| `users` | id, nombre, email, password, **tipo** (`interno`/`establecimiento`), **establecimiento_id** (nullable) | Filament Shield + aislamiento por establecimiento |
| `activity_log` | spatie | Auditoría completa |

### 7.3 Recepción mensual (nuevo módulo)

| Tabla | Contenido | Notas |
|---|---|---|
| `establecimiento_series` | id_establecimiento, serie, requerida | Qué series debe reportar cada establecimiento; se siembra del histórico y el admin la ajusta |
| `ventanas_carga` | id, idservicio, ano, mes, apertura, cierre, habilitada, notas, creada_por, timestamps | **Control del administrador**: abrir/cerrar/prorrogar; por defecto días 1–5 hábiles **con feriados de Chile precargados y editables** |
| `recepciones` | id, idservicio, id_establecimiento, serie, mes, ano, archivo, hash, **fecha_carga**, **en_plazo**, **es_segunda_subida** (bool), estado, version, usuario_id, carga_id, timestamps | Un registro por envío; `version` incrementa en reenvíos; `es_segunda_subida` = true si es reenvío fuera de plazo después del cierre |
| `recepcion_observaciones` | id, recepcion_id, autor_id, tipo (`observacion`/`rechazo`), texto, created_at | Diálogo equipo REM ↔ establecimiento |
| `recepcion_eventos` | id, recepcion_id, estado_anterior, estado_nuevo, usuario_id, created_at | Historial de estados (además del activity log) |

**Estados de una recepción:** `recibida` → `validando` → `validada` | `con_errores` → `en_revision` → `aceptada` | `observada` → (reenvío, `version+1`) | `rechazada`.

- `en_plazo` se calcula comparando `fecha_carga` con la ventana vigente (cierre).
- **Segunda subida**: si la ventana está cerrada y el establecimiento ya tiene una recepción `aceptada` o `validada`, puede subir una vez más (versión 2). Queda marcada con `es_segunda_subida = true` y **no consolida automáticamente**; el equipo REM revisa y decide si acepta.
- Al `aceptar`, el envío se consolida en `datos` (misma ruta de guardado de M1, en una transacción) y se enlaza con `cargas`.

### 7.4 Reglas de integridad equivalentes a las del original

- Borrado de datos por establecimiento+mes+año+serie, **con transacción** (el original no la tenía).
- `hist_carga` reconstruible con `GROUP BY` sobre `datos` (misma lógica que `ActDBServ`).
- Toda fila insertada conserva `fecha_ingreso` para auditoría.
- Un establecimiento no puede tener dos recepciones `aceptadas` para la misma serie/mes/año: la nueva versión supersede a la anterior.

---

## 8. Módulos del sistema

### M1 — Carga y validación (interna)

- Subida de **un archivo** o **carpeta completa** (drag & drop) por el equipo REM.
- Sirve también para **carga asistida**: archivos que un establecimiento envíe por un canal alternativo (sin conectividad) se suben en su nombre y quedan registrados con `origen=interno`.
- Detección de extensión por año: ≥2017 solo `.xlsm`; antes, `.xls/.xlsx/.xlsm`.
- Validación completa en worker: encabezado, establecimiento, mes, año, serie, versión, hojas, prestaciones, decimales, excepciones.
- Pantalla de resultado: **datos correctos**, **errores** (hoja/línea/columna/valor) y **datos de control**, igual que las grillas del original.
- Confirmación explícita para consolidar (el original: botón "Guardar" tras validar).
- Si ya existe carga para ese establecimiento/mes/año/serie: aviso y opción de reemplazar (con transacción).

### M2 — Dashboard e informes

| Informe | Equivalente original | Implementación |
|---|---|---|
| **Panel de recepción y cumplimiento** | *(nuevo)* | Matriz establecimiento × serie por mes con **estado y fecha de carga**, pendientes, atrasados y % de cumplimiento; exportable |
| Matriz establecimientos × meses | `EstabCargados` | Tabla pivote con "X" por serie; exportable |
| Establecimientos sin datos de control | `InformeControl` | Cruce establecimientos × datos_control |
| Log de errores | `LogErrorCargados` / `LogErrorMultiples` | Filtros por serie/mes/establecimiento; export |
| Comparativos | `Comparativos` / `ComparativoMes` | Entre establecimientos y entre meses |
| Duplicidad | `Duplicidad` | Detección de registros repetidos |
| Auditorías de datos | `Prestacion`, `Decimales`, `ValoryText` | Prestaciones, decimales, textos en valores |
| Exportación a Excel | `Export` | Export libre con filtros |

Además: **tarjetas de resumen** en el home del dashboard (archivos recibidos del mes, pendientes por establecimiento, errores abiertos, fuera de plazo).

### M3 — Generación del archivo Minsal

Ver §11. Botón por año/serie → genera `.zip` descargable, idéntico al actual.

### M4 — Integración con el Minsal

Ver §12. Dos acciones: **actualizar catálogos** y **descargar histórico** (por serie/año).

### M5 — Administración

- Gestión de usuarios internos y **cuentas de establecimiento**: **titular + suplente** (dos usuarios nominales por establecimiento; alta, baja, reseteo de clave, vínculo al establecimiento).
- **Gestión de ventanas de carga**: crear la ventana del mes (por defecto días 1–5 hábiles), editar fechas (feriados), **abrir/cerrar manualmente**, prorrogar; vista de calendario.
- Configuración de qué **series reporta cada establecimiento** (`establecimiento_series`).
- Visualización de catálogos sincronizados (solo lectura + botón de actualización).
- Configuración del servicio activo (equivalente a `Seteo`, pero **modificable**, no irreversible).
- Auditoría (activity log) y estado del sistema (versión de BD local vs servidor, último sync, backups).
- **Tema visual**: personalización de colores, logo Osorno, modo dark/light.

### M6 — Migración (one-shot)

Comando `php artisan rem:migrar --desde=2024 --hasta=2026` (ver §13).

### M7 — Portal de Recepción de Información Mensual (establecimientos)

- **Acceso**: **titular + suplente** (dos cuentas nominales por establecimiento, `Usuario Establecimiento`, con recuperación de clave; ambas ven y suben lo mismo).
- **Calendario**: muestra el mes habilitado, fecha de apertura y cierre, y el estado de la ventana (abierta/cerrada). **Feriados de Chile** visibles.
- **Formulario de carga**: habilitado solo si la ventana está abierta (o si el admin lo abre manualmente); el establecimiento elige la serie de las que le corresponden y sube su archivo.
- **2da subida (fuera de plazo)**: si la ventana está cerrada y ya hay una recepción `aceptada`/`validada`, se habilita **una única subida adicional** (versión 2), marcada como "2da subida" y "fuera de plazo"; no consolida sin revisión del equipo REM.
- **Validación inmediata**: si el archivo tiene errores, se los mostramos con hoja/línea/columna/valor antes de dar por enviado.
- **Comprobante y trazabilidad**: cada envío guarda fecha/hora, archivo (hash), versión y estado; el establecimiento ve su historial completo.
- **Observaciones**: el equipo REM puede observar un envío; el establecimiento lo ve y reenvía corregido (nueva versión).
- **Estado de cumplimiento propio**: el establecimiento ve qué series tiene pendientes en el mes.
- **Aislamiento**: solo ve su establecimiento; nunca datos de otros ni información interna del servicio.
- **Tema visual**: colores y logo del Servicio de Salud Osorno; selector dark/light.
- **Notificaciones por correo**:
  - **Recordatorio pre-vencimiento** (día 3 hábil) a establecimientos con series pendientes.
  - **Confirmación post-carga** inmediata: fecha/hora, versión, estado (en plazo / fuera de plazo / 2da subida), resumen de validación (correctos / errores / control).

---

## 9. Reglas de validación a preservar

Estas reglas se implementan **exactamente** como en el original (verificado en `CargaDatos.cs` durante el análisis) y se aplican **igual** a los archivos que llegan por el portal:

### 9.1 Encabezado (hoja `NOMBRE`)

| Dato | Celda |
|---|---|
| Establecimiento | `C3+D3+E3+F3+G3+H3` |
| Mes | `C6+D6` |
| Mes en texto | `B6` |
| Año | `B7` |
| Serie | `B17` (primeros 7–8 caracteres) |
| Versión del formato | `A9` (primeros 12 caracteres) |

### 9.2 Validaciones previas

1. El establecimiento pertenece al servicio configurado (`establecimientos` filtrado por `idservicio`).
2. Mes texto ↔ número (`ENERO`=01 … `DICIEMBRE`=12).
3. Año y serie del archivo coinciden con la selección.
4. Versión del formato coincide con `cat_versiones` para mes/año/serie (mapeo `serie_excel` ↔ `serie` interna).

### 9.3 Posicionamiento (catalog-driven)

- `cat_thojas` → hojas a leer y rango de celdas por serie/año.
- `cat_diccionario` → línea y columnas (`inicio`…`fin`) de cada prestación.
- `cat_excep` / `cat_colexcep` → celdas que se ignoran.

### 9.4 Reglas numéricas

| Condición | Resultado |
|---|---|
| No numérico o negativo | Error |
| Más de un punto decimal | Error (corta la fila) |
| Exactamente un decimal | Error, **salvo** hojas `A09`, `A21`, `SERIE BS`, `SERIE D` |
| Entero | Válido |

### 9.5 Hojas de control

Nombre de hoja ≥ 6 caracteres → va a `datos_control` (col01–col05).

### 9.6 Filas vacías

Filas con todos los valores en 0/null no se insertan (misma regla que el original).

---

## 10. Flujos de carga y recepción

### 10.1 Flujo del establecimiento (portal)

```
Establecimiento inicia sesión
        │
        ▼
Calendario: ¿ventana habilitada para el mes?
        ├─ NO → "Recepción cerrada. Próxima apertura: <fecha>."
        │        (puede consultar su historial y sus series pendientes)
        │        └─ Si ya subió una vez → opción "2da subida (fuera de plazo)" (máx. 1)
        └─ SÍ → elige serie(s) que le corresponden → sube archivo(s)
                   │
                   ▼
        [Job: Validar]  ── falla encabezado/serie/año → estado "rechazada" + motivo
                   │
                   ├─ errores de prestaciones → estado "con_errores"
                   │      └─ el establecimiento ve hoja/línea/columna/valor
                   │         y sube una versión corregida (version+1)
                   │
                   └─ sin errores → estado "validada"
                          │
                          ▼
        El equipo REM revisa el envío
              ├─ acepta → se consolida en `datos` (transacción) → estado "aceptada"
              └─ observa → estado "observada" + comentario → el establecimiento reenvía
```

**Regla de 2da subida:** si la ventana está cerrada y el establecimiento ya tiene una recepción `aceptada` o `validada`, se habilita **una única subida adicional** (versión 2). Queda marcada con `es_segunda_subida = true`, cuenta como "fuera de plazo" y **no consolida automáticamente**: el equipo REM revisa y decide si acepta.

### 10.2 Flujo interno (equipo REM)

```
Operador sube archivo(s) (de un establecimiento, o carga masiva)
        │
        ▼
[Job: Validar]  ── error de lectura encabezado → estado "rechazado" + motivo
        │
        ├─ Encabezado OK → valida establecimiento, mes, año, serie, versión
        ├─ Carga catálogos (Thojas/Diccionario/Excep) en memoria
        ├─ Recorre hojas y prestaciones → filas correctas / errores / control
        ▼
Pantalla de resultados (correctos | errores | control)
        │
        ├─ Operador corrige y/o confirma
        ▼
[Job: Guardar]  transacción:
        ├─ DELETE datos del establecimiento/mes/año/serie (si reemplazo)
        ├─ INSERT masivo de filas correctas
        ├─ INSERT datos_control
        ├─ UPSERT hist_carga
        ├─ INSERT log_errores
        └─ COMMIT  → estado "guardado"
```

**Diferencia con el original:** todo el guardado ocurre en una transacción; un fallo a mitad de camino **no deja el año parcialmente borrado**.

### 10.3 Ventana de carga

| Aspecto | Regla |
|---|---|
| Creación | El sistema propone la ventana del mes: **días 1 a 5 hábiles**; **feriados de Chile** (nacionales y regionales de Los Lagos) se precargan y el administrador edita/agrega fechas antes de guardar |
| Control manual | El administrador puede **abrir, cerrar o prorrogar** en cualquier momento, con registro de quién lo hizo y notas |
| Envío fuera de ventana | Se acepta pero queda marcado **"fuera de plazo"**; nunca se descarta información |
| **Segunda subida fuera de plazo** | Si ya hay una recepción `aceptada` o `validada`, se permite **una única subida adicional** (versión 2), marcada `es_segunda_subida = true`; no consolida sin revisión del equipo REM |
| Visualización | El establecimiento ve siempre fechas, estado y contador de versión; el equipo REM ve el cumplimiento por establecimiento y serie |
| Recordatorios | **Automáticos**: (a) **día 3 hábil** — recordatorio a establecimientos pendientes; (b) **post-carga** — confirmación con fecha, versión, estado (en plazo/fuera de plazo/2da subida) y resumen de validación |

---

## 11. Generación del archivo Minsal

Replica exacta del módulo `Minsal.cs`:

1. Verificar que hay datos para el año/serie seleccionado.
2. Copiar la plantilla `MASTER.mdb` como archivo base.
3. Volcar desde MySQL a las tablas `Datos`, `DatosC` y la fila de control en `Registros` (cantidad, idservicio, serie, mes límite, fecha, año).
4. Comprimir con GZip → `<nombre>.zip`.
5. Descargar el `.zip` al navegador; el `.mdb` intermedio se elimina.

**Implementación técnica:** PHP `COM('ADODB.Connection')` con `Provider=Microsoft.ACE.OLEDB.12.0`, insertando por lotes. El proveedor ACE ya está en el servidor (lo usa ValidadorRem), por lo que no hay dependencia nueva.

**Prueba de paridad (hito H3):** con el mismo conjunto de datos de entrada, generar el `.zip` desde WebCargaREM y desde ValidadorRem, y comparar el contenido de las tablas `Datos`, `DatosC` y `Registros`. Deben ser equivalentes fila a fila.

---

## 12. Integración con los servicios del Minsal

### 12.1 Evidencia

- WSDL y endpoints HTTP GET **vivos y respondiendo** (ver §4.1).
- `DatosSerie(serie, servicio, ano)` confirmado para el histórico de datos.
- La versión de BD del servidor (`2.00.417`) se puede leer en tiempo real.
- Los servicios exponen GET además de SOAP.

### 12.2 Estrategia

| Vía | Uso | Implementación |
|---|---|---|
| **Primaria: HTTP GET + XML** | Catálogos (`/datos`, `/servicio`, `/diccionario`, `/Thoja`, `/Excep`, `/ColExcep`, `/Version`) e histórico | Guzzle + SimpleXML; sin extensiones adicionales |
| Respaldo: SOAP | Si un endpoint requiere POST SOAP | `SoapClient` (extensión `php_soap`) |
| Último recurso: importar `.mdb` | Si el Minsal no responde | Importador desde `Global.mdb` local (solo lectura) |

- Las actualizaciones corren como **jobs** con barra de progreso (equivale a `Actualizador`).
- La descarga de histórico pide confirmación, **borra y reinserta dentro de una transacción**, y reconstruye `hist_carga` (igual que `ActDBServ`).
- Se registra en auditoría cada sincronización (quién, cuándo, qué tablas, cuántas filas).

---

## 13. Migración histórica 2024–2026

**Origen:** copias de los `.mdb` en un directorio temporal (las originales no se tocan).

| Paso | Acción |
|---|---|
| 1 | Copiar `Global.mdb`, `Trans.mdb` y los `Datos/ControlSERIE<X><año>.mdb` de 2024–2026 a un directorio de trabajo |
| 2 | Importar catálogos (`mpEstablecimiento`, `mpServicio`, `Diccionario`, `Thojas`, `Excep`, `colexcep`, `version`) |
| 3 | Importar `datos` y `datos_control` de cada serie/año |
| 4 | **Sembrar `establecimiento_series`** desde el histórico: qué series reportó cada establecimiento en 2024–2026 (el admin ajusta casos borde) |
| 5 | Reconstruir `hist_carga` con `GROUP BY` (misma lógica del original) |
| 6 | Verificación: contar filas origen vs destino por serie/año/establecimiento |

**Script:** comando Artisan `rem:migrar` idempotente (re-ejecutable sin duplicar). Volumen estimado: ~2,3M filas — minutos en MySQL.

---

## 14. Seguridad y auditoría

| Control | Implementación |
|---|---|
| Autenticación | Login propio en BD, hash bcrypt/argon2, bloqueo por intentos fallidos |
| Autorización | **4 roles**: Operador (carga interna y revisión), Supervisor (informes, revisión y export), Admin (todo + ventanas, cuentas y catálogos), **Usuario Establecimiento** (solo su establecimiento) |
| **MFA** | **Obligatorio en producción** para Admin y Supervisor (TOTP: Google Authenticator, Authy, etc.); opcional en desarrollo/pruebas |
| **Cuentas por establecimiento** | **Titular + suplente**: dos usuarios nominales (`tipo=establecimiento`, `establecimiento_id`) con acceso completo al portal del establecimiento |
| Aislamiento por establecimiento | Filtro global por `establecimiento_id` + políticas: un establecimiento solo ve y sube lo suyo |
| Auditoría | `activity_log` + `recepcion_eventos`: carga, validación, guardado, reemplazo, borrado, sync, apertura/cierre de ventana, login, IP, MFA |
| Consultas | Eloquent/query builder **parametrizado** — se elimina la concatenación SQL del original |
| Transacciones | Todo guardado/borrado/sync en transacción |
| Backups | `mysqldump` diario por tarea programada + **dump previo obligatorio** antes de borrados masivos y sincronizaciones de histórico |
| Archivos subidos | Directorio fuera del webroot, nombre aleatorio, extensión y tamaño validados, hash SHA-256; **retención indefinida (borrado manual por admin)** |
| Exposición de red | Portal en subdominio con **HTTPS obligatorio**, rate limiting y bloqueo por intentos; panel interno restringido a intranet/VPN (ver §6.3) |
| Protección de datos | Minimización, acceso nominal, retención configurable, registro de fecha/IP de acceso (Ley 19.628 y 21.719 como referencia) |
| Secretos | `.env` fuera de control de versiones |
| **Notificaciones por correo** | Recordatorio pre-vencimiento (día 3 hábil) y confirmación post-carga; canal SMTP institucional |

---

## 15. Roadmap por hitos

Sin fecha fija; cada hito termina con criterio de salida verificable.

| Hito | Contenido | Criterio de salida |
|---|---|---|
| **H0 — Base** | Proyecto Laravel+Filament (dos paneles), login, roles, esquema MySQL, catálogos sembrados desde Global.mdb | Login con 4 roles; catálogos visibles; portal y panel separados |
| **H1 — Carga y validación** | M1 completo (individual y masiva), jobs, pantalla de resultados | Un Excel real de 2026 se valida y guarda con resultado idéntico a ValidadorRem |
| **H2 — Informes** | M2 (matriz, control, log errores, comparativos, duplicidad, auditorías, export) | Cada informe reproduce el resultado del original con los mismos datos |
| **H3 — Archivo Minsal** | M3 + prueba de paridad | .zip generado con contenido equivalente al del ValidadorRem |
| **H4 — Integración** | M4 (catálogos + histórico vía HTTP GET, respaldo SOAP/importador) | Sync de catálogos contra el servidor real y descarga de histórico de prueba |
| **H5 — Portal de recepción** | M7 + ventanas de carga + panel de cumplimiento + cuentas de establecimiento | Un establecimiento de prueba completa el ciclo: carga en ventana → validación → revisión → observación → reenvío → aceptación, con fechas y estados visibles |
| **H6 — Migración y cierre** | M6 (2024–2026), siembra de `establecimiento_series`, auditoría, backups automatizados, documentación | Conteos origen=destino; backup y restore probados |
| **H7 — Transición** | Operación en paralelo, capacitación a establecimientos, corte | 2 meses sin divergencias; archivo aceptado por el portal; establecimientos operando por el portal |

---

## 16. Transición con ValidadorRem

- **Durante 1–2 meses**: ambos sistemas operan en paralelo. ValidadorRem sigue siendo la fuente oficial ante el Minsal hasta el corte.
- **Criterio de corte**: dos ciclos mensuales sin divergencias en informes y al menos un archivo generado por WebCargaREM aceptado por el portal.
- **Después del corte**: los `.mdb` quedan archivados como histórico de solo lectura; el escritorio se desinstala.
- **Riesgo controlado**: si algo falla, se puede volver al escritorio durante la transición sin pérdida (ambos leen los mismos Excel).

### Incorporación de los establecimientos al portal

1. **Mes 0**: alta de cuentas de los 86 establecimientos y capacitación breve (carga, errores, observaciones).
2. **Mes 1**: convivencia — se acepta el correo y el portal en paralelo; el equipo REM registra en el sistema lo que llegue por correo (carga asistida).
3. **Mes 2 en adelante**: el portal es el canal oficial; el correo queda solo como contingencia.
4. **Criterio de cierre de la transición del portal**: los 86 establecimientos con al menos un envío registrado (por portal o carga asistida).

---

## 17. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| El portal Minsal cambia el formato de carga | Mantener ValidadorRem disponible durante transición; monitorear el portal |
| Los servicios del Minsal caen o cambian | Respaldo SOAP + importador desde `Global.mdb`; sync manual |
| Excel sin valores de fórmula cacheados | Detectar y rechazar con mensaje claro ("abra y guarde el archivo en Excel") |
| `COM`/ACE no disponible en el servidor | Es el mismo servidor que ya ejecuta ValidadorRem; verificar en H0 |
| **Establecimiento sin conectividad o equipamiento** | Carga asistida por el equipo REM en su nombre (M1) + soporte telefónico |
| **Incumplimiento de la ventana de carga** | Panel de pendientes, **recordatorios automáticos (día 3)**, prórrogas controladas por el admin |
| **Envío tardío** | Se acepta marcado "fuera de plazo" (cuenta como error de cumplimiento); **se permite 2da subida**; no se pierde el dato y el atraso queda registrado |
| **Suplantación de cuenta de establecimiento** | Cuentas nominales verificadas por el admin, bloqueo por intentos, registro de IP/fecha |
| **Reenvíos que confunden versiones** | Versionado explícito + flag `es_segunda_subida`; solo la última versión aceptada consolida |
| **Portal expuesto sin hardening** | Proxy inverso con TLS, WAF/límites, **MFA obligatorio en producción para Admin/Supervisor**, revisión de configuración previa a la apertura |
| XAMPP expuesto | Panel interno solo intranet, firewall, HTTPS, backups |
| Crecimiento de datos | MySQL sin límite de 2 GB; índices y particionado por año si crece |
| Cambio de reglas REM cada año | Catálogo actualizable sin recompilar (misma ventaja del original) |

---

## 18. Decisiones resueltas (todas)

1. ~~Conectividad~~ → **Subdominio institucional con HTTPS** (§6.3).
2. ~~Ventana de carga~~ → **Días 1–5 hábiles + feriados Chile editables** (§10.3, M5).
3. ~~Envíos fuera de plazo~~ → **Aceptar + marcar + 2da subida (v2)** (§7.3, §10.1, §10.3).
4. ~~Recordatorios~~ → **2 automáticos: día 3 hábil + confirmación post-carga** (§10.3, M7, §14).
5. **MFA** → **Obligatorio solo en producción** (Admin + Supervisor) (§14, §17).
6. **Cuentas por establecimiento** → **Titular + suplente** (dos cuentas nominales) (M5, M7).
7. **Tema visual** → **Personalizado (Osorno)**: colores, logo, dark/light (§8, M7).
8. **Retención Excel** → **Indefinido (borrado manual por admin)** (§14).
9. **Estructura menús** → **5 grupos funcionales**: Carga, Informes, Minsal, Administración, Recepción (§8, §15).
10. **Verificar XAMPP** → **Verificado**: PHP 8.2.12 (compatible Laravel 12); extensiones base OK; **pendiente habilitar `soap` y `com_dotnet` en php.ini** (DLLs presentes) (H0).

---

## Anexo: mapeo módulo original → módulo web

| Original (WinForms) | Web (WebCargaREM) |
|---|---|
| `Principal` | Layout + navegación Filament |
| `CargaDatos` | M1 — carga individual |
| `CargaDatosMultiple` | M1 — carga masiva |
| `Minsal` | M3 — generador .mdb/.zip |
| `EstabCargados` | M2 — matriz estab×mes |
| `InformeControl` | M2 — control faltante |
| `Informe1` | M2 — informe dinámico |
| `Comparativos` / `ComparativoMes` | M2 — comparativos |
| `Duplicidad` | M2 — duplicidad |
| `Prestacion` / `Decimales` / `ValoryText` | M2 — auditorías |
| `LogErrorCargados` / `LogErrorMultiples` | M2 — log de errores |
| `Export` | M2 — export a Excel |
| `Eliminar` | M5 — administración de datos (con confirmación y backup) |
| `Seteo` | M5 — configuración del servicio (modificable) |
| `Actualizacion` / `Actualizador` | M4 — sync de catálogos |
| `ActDBServ` | M4 — descarga de histórico |
| `Optimiza` | **No aplica** (MySQL no requiere compactación) |
| `bases` (plantillas) | Modelo de datos + migraciones Laravel |
| `primera` / `Cargando` / `buscaActualizacion` | Pantallas de estado y progreso del panel |
| *(nuevo — no existe en el original)* | **M7 — Portal de recepción, ventanas de carga y panel de cumplimiento** (reemplaza el envío por correo) |

---

*Documento de idea consolidado a partir de `INFORME.md`, el levantamiento del 23-10-2026, las pruebas de conectividad de solo lectura y la ampliación con el Sistema de Recepción de Información Mensual. No modifica ningún componente del sistema actual.*
