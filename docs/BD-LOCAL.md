# BD LOCAL — Bases de datos del sistema anterior (ValidadorRem)

**Ubicación física:** `C:\xampp\htdocs\www\CargasREM\BD\`
**Estado en git:** IGNORADA (regla `/BD/` en `.gitignore`). **Esta carpeta nunca se sube a GitHub ni a ningún repositorio remoto.**

## Por qué no se versiona

1. **Dato sensible**: contiene catálogos completos del Minsal (`Global.mdb`) y estadísticas clínicas reales del Servicio de Salud Osorno (2012–2026) — datos protegidos según RNF-017 (Ley 19.628 / 21.719).
2. **Tamaño**: `Global.mdb` ≈ 418 MB; `DatosSERIEA2025.mdb` ≈ 170 MB; el total supera 700 MB — inviable como parte del repo.
3. **Son copias de referencia en solo lectura**: los originales viven en la instalación productiva de ValidadorRem; aquí se usan como insumo de H0 (POCs COM/ACE), fixtures de paridad y migración histórica. Mustejarse como copias intocadas (ver `specs/PLAN.md` §9.11 y `specs/SPECS.md` RN-047/Flujo FLUJO-06).

## Inventario y rol de cada archivo (según `INFORME.md`)

### Plantillas (se copian para "construir en cada carga" — mismo patrón que replicará WebCargaREM)

| Archivo | Rol en ValidadorRem | Uso en WebCargaREM |
|---|---|---|
| `MASTER.mdb` | **Plantilla del archivo de carga al Minsal**: tablas `Datos` (56 col.), `DatosC`, `Registros` (CantReg, idservicio, Serie, Mes, Fecha, ano) | RF-014 (RN-026): se copia esta plantilla y se le vuelcan `datos`/`datos_control` antes de GZip → `.zip` |
| `MasterDB.mdb` | Plantilla de bases de `DatosSERIE<año>.mdb` (tabla `Datos` con Col01–Col50) | Referencia de tipos de columnas (PD-PLAN-05) en POC H0 (T-006 de specs/TASK.md) |
| `MasterC.mdb` | Plantilla de bases `ControlSERIE<año>.mdb` (col01–col05) | Referencia de tipos para `datos_control` |

### Bases históricas reales (insumo de migración y paridad)

| Archivo | Contenido | Uso en WebCargaREM |
|---|---|---|
| `DatosSERIE{A,BM,BS,D,F,P}{2012,2025,2026}.mdb` | Datos consolidados por serie y año | **Migración M6** (`rem:migrar`, T-068..T-072) y **fixtures de paridad zip** (T-042/T-065) |
| `ControlSERIE{A,BM,BS,D,P}{2025,2026}.mdb` | Hojas de control/resumen por serie y año | Ídem (los 2012 se omiten: fuera del alcance del MVP) |
| `Global.mdb` | Catálogos completos del Minsal (mpEstablecimiento, Diccionario, Thojas, Excep, colexcep, versiones, mpServicio…) | **Seed inicial de catálogos** (T-025) y último recurso del sync (RN-031) |

### Bases de trabajo "por cada carga" (patrón operativo del sistema anterior)

| Archivo | Rol en ValidadorRem | Equivalente web |
|---|---|---|
| `Trans.mdb` | Estado transaccional local: `HistCarga`, `LogError`, `SetServicio` (idservicio elegido, irreversible en el sistema viejo) | Reemplazado por tablas MySQL `hist_carga`, `log_errores` y servicio activo configurable (RF-021) |
| `Temporal.mdb` | `Cargados`: matriz establecimiento × meses — se borra y regenera en cada ejecución de EstabCargados | Reemplazado por consulta SQL directa (RF-007) |
| `TemporalControl.mdb` | Informe control: estab sin hojas de control | Consulta SQL (RF-008) |
| `TemporalIncorrectos.mdb` | `INCORRECTO`: errores de la carga masiva, exportable | Tabla `log_errores` + informe (RF-002/RF-009) |
| `Control.mdb` | **Huérfano**: no aparece referenciado en el código decompilado | Sin uso previsto |

> ⚠️ Los `Temporal*` y `Trans.mdb` se **regeneran/reescriben en cada carga** en el sistema viejo. Las copias de esta carpeta corresponden al último estado del sistema — **solo sirven como referencia forense**, no como datos a migrar (la migración toma catálogos e histórico de `Global.mdb` + `DatosSERIE*`/`ControlSERIE*`).

## Reglas de manejo

1. **Nunca commitear, nunca subir**: protegido por `/BD/` en `.gitignore`; verificar con `git status` que no aparezca nunca con `??` ni `A`.
2. **Solo lectura**: WebCargaREM lee estos archivos (COM/ADODB); nunca escribe. Antes de la migración, copiar a `storage/rem/migracion/` y trabajar sobre la copia — los originales quedan intocados y se verifica con hash SHA-256 antes/después (T-070 en `specs/TASK.md`).
3. **No copiar a producción por el canal web**: estos archivos no se exponen vía Apache (están fuera de `public/`); mover solo por red local o terminal.
4. Si se agrega otro archivo `.mdb` aquí, queda cubierto por la regla general; si se necesita un fixture pequeño de prueba versionado, debe anonimizarse/depurarse y vivir en `tests/`, no en `BD/`.

## Trazabilidad

- Documentado a partir del análisis de `INFORME.md` (análisis estático de `ValidadorRem.exe`).
- Tareas dependientes de estos activos: T-004/T-005/T-006 (POCs), T-025 (seed catálogos), T-042/T-065 (paridad zip), T-068..T-072 (migración) — ver `specs/TASK.md`.
