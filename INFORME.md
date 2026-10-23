# INFORME TÉCNICO — Sistema ValidadorRem (Cargas REM 2016 — Minsal)

**Fecha del análisis:** 22 de octubre de 2026
**Sistema analizado:** `ValidadorRem.exe` — Validador de Cargas REM (Registro Estadístico Mensual)
**Ubicación del sistema:** `C:\Program Files (x86)\Minsal\Cargas Rem 2016`
**Versión interna de la BD (Global.mdb → Software):** CargaRem 2.00.416
**Plataforma:** Windows Forms, .NET Framework 4.8 (aplicación VB.NET compilada)

> **Nota de integridad:** este análisis fue **100% de solo lectura**. No se modificó ningún archivo del sistema
> (ni ejecutables, ni bases .mdb, ni configuración). El código fuente decompilado se generó en un directorio
> externo temporal (`C:\Users\Lenovo\AppData\Local\Temp\opencode\ValidadorRem_src`) y el índice del grafo
> en una base propia del MCP, sin escribir nada en la carpeta del sistema.

---

## Índice

1. [Metodología del análisis](#1-metodología-del-análisis)
2. [Qué es el sistema](#2-qué-es-el-sistema)
3. [Arquitectura y stack tecnológico](#3-arquitectura-y-stack-tecnológico)
4. [Flujo de lectura de los archivos Excel (paso a paso)](#4-flujo-de-lectura-de-los-archivos-excel-paso-a-paso)
5. [Flujo de guardado en las bases .mdb](#5-flujo-de-guardado-en-las-bases-mdb)
6. [Mapa completo de todas las bases de datos (esquema verificado)](#6-mapa-completo-de-todas-las-bases-de-datos-esquema-verificado)
7. [Generación del archivo de carga al Minsal](#7-generación-del-archivo-de-carga-al-minsal)
8. [Mantención de las bases: servicios web y optimización](#8-mantención-de-las-bases-servicios-web-y-optimización)
9. [Observaciones técnicas y riesgos detectados](#9-observaciones-técnicos-y-riesgos-detectados)
10. [Anexo A: módulos del sistema y su rol](#anexo-a-módulos-del-sistema-y-su-rol)
11. [Anexo B: llamadores confirmados con el grafo](#anexo-b-llamadores-confirmados-con-el-grafo)
12. [Anexo C: flujo end-to-end resumido](#anexo-c-flujo-end-to-end-resumido)

---

## 1. Metodología del análisis

El sistema es un ejecutable .NET compilado (sin código fuente disponible en la instalación). Para poder
analizarlo con **codebase-memory-mcp** se siguió este procedimiento, sin alterar el sistema:

| Paso | Acción | Resultado |
|---|---|---|
| 1 | Lectura de `ValidadorRem.xml` (documentación del ensamblado) | Clases proxy de servicios web |
| 2 | Decompilación con ILSpy (`ilspycmd` 9.1, modo proyecto) a un directorio externo | 96 archivos `.cs` en `Temp\opencode\ValidadorRem_src` |
| 3 | Indexación con codebase-memory-mcp (`index_repository`, modo `full`) | Proyecto `ValidadorRem-decompiled`: **1.971 nodos, 8.799 aristas** |
| 4 | Análisis con herramientas del grafo: `get_architecture`, `search_graph`, `get_file_outline`, `query_graph` (Cypher) | Estructura, hotspots, capas, llamadas |
| 5 | `check_index_coverage` sobre los 13 archivos citados | Sin gaps de cobertura (best-effort limpio) |
| 6 | Verificación del esquema real de los `.mdb` vía `Microsoft.ACE.OLEDB.12.0` | Tablas y columnas documentadas en §6 son las **reales** |

**Cobertura del análisis:** los 96 archivos fuente decompilados fueron indexados por completo
(único archivo excluido: `app.ico`, ignorado por diseño del indexador).

---

## 2. Qué es el sistema

Aplicación de escritorio que **valida, consolida y prepara la carga de los archivos Excel REM**
(Registro Estadístico Mensual) que los Servicios de Salud envían al Minsal de Chile.

Funciones principales:

1. **Validación local** de los Excel REM de cada establecimiento (estructura, posición de datos, valores numéricos, versiones).
2. **Consolidación** de los datos validados en bases Access locales, una por serie y año.
3. **Generación del archivo de carga** (.mdb + .zip) que se sube al portal central `cargasrem.minsal.cl`.
4. **Descarga del histórico** del servicio desde el servidor (para reconstruir las bases locales).
5. **Informes y auditoría**: establecimientos cargados, errores, comparativos, duplicados, exportaciones.
6. **Actualización de catálogos** (diccionarios, establecimientos, servicios) desde servicios web SOAP del Minsal.

**Series manejadas (6):** `SERIE A`, `SERIE BS`, `SERIE BM`, `SERIE D`, `SERIE P`, `SERIE F`.

---

## 3. Arquitectura y stack tecnológico

### 3.1 Componentes (verificados en el código decompilado y los DLL instalados)

| Componente | Tecnología | Uso en el sistema |
|---|---|---|
| UI | Windows Forms | Formulario principal con menú + formularios por función |
| Lectura de Excel | **NPOI** (HSSF/XSSF: `NPOI.dll`, `NPOI.OOXML.dll`, …) | Lee `.xls/.xlsx/.xlsm`, evalúa fórmulas |
| Office Interop | `Microsoft.Office.Interop.Excel.dll` | Referencia del proyecto original; la lectura actual usa NPOI; hay gestión de procesos EXCEL (`KillAllAddedExcelProcesses`) |
| Persistencia | `System.Data.OleDb` + **`Microsoft.ACE.OLEDB.12.0`** | Todas las bases Access (.mdb) |
| Compactación | **JRO** (`Interop.JRO.dll`, `JetEngine`) | Módulo `Optimiza`: compacta los .mdb |
| Remoto | **Servicios web SOAP ASMX** | Actualización de catálogos y descarga de históricos |
| Compresión | `System.IO.Compression` (GZip) + `ICSharpCode.SharpZipLib.dll` | Genera el .zip de carga |

### 3.2 Servicios web consumidos (desde `ValidadorRem.exe.config`)

| URL (endpoint) | Rol |
|---|---|
| `https://cargasrem.minsal.cl/webservice/WebService1.asmx` | Versión de la base de datos central |
| `https://cargasrem.minsal.cl/webservice/versionejecutable.asmx` | Versión del ejecutable (bloquea la app si está desactualizada) |
| `https://cargasrem.minsal.cl/webservice/webservicedb.asmx` | Catálogos: `datos` (establecimientos), `servicio`, `diccionario`, `Thoja`, `Excep`, `ColExcep` |
| `https://cargasrem.minsal.cl/webservice/CargaSerie.asmx` | Proxy de series (carga por serie) |
| `https://cargasrem.minsal.cl/webservice/CargaSerieServ.asmx` | Descarga del histórico de datos por serie/servicio/año (`ActDBServ`) |
| `https://cargasrem.minsal.cl/webservice/CargaControlServ.asmx` | Descarga del histórico de control por serie/servicio/año |
| `https://cargasrem.minsal.cl/webservice/WebService3.asmx` | Proxy LlenaSerie |
| `https://cargasrem.minsal.cl/webservice/Optimiza_Up.asmx` | Tablas a actualizar en `Actualizador` |
| `http://intradeis.minsal.cl/webservicePrueba/Optimiza_Up.asmx` | Entorno de prueba (ContUp) |

### 3.3 Estructura del código (del grafo: 256 clases, 461 métodos)

- **Formularios funcionales:** `Principal` (menú), `CargaDatos` (carga individual), `CargaDatosMultiple`
  (carga masiva), `Minsal` (generación archivo de carga), `EstabCargados`, `InformeControl`, `Informe1`,
  `Comparativos`, `ComparativoMes`, `Duplicidad`, `Prestacion`, `Decimales`, `ValoryText`, `Eliminar`,
  `Export`, `LogErrorCargados`, `LogErrorMultiples`, `Seteo`, `Optimiza`, `Actualizador`, `Actualizacion`,
  `ActDBServ`, `buscaActualizacion`, `primera`, `Cargando`.
- **Módulo central de bases:** `bases.cs` (creación de bases por plantilla, borrados, utilidades).
- **Proxies SOAP:** namespaces `Datos`, `DatosSerie`, `ExtraeDatos`, `ExtraeDatosC`, `LlenaSerie`,
  `ContUp`, `TabUp`, `ValidaVersionEjec`, `version`.

---

## 4. Flujo de lectura de los archivos Excel (paso a paso)

> Verificado en `CargaDatos.cs` (carga individual) y `CargaDatosMultiple.cs` (carga masiva).

### 4.1 Selección del archivo

`CargaDatos.Button1_Click` (líneas 2599–2615): `OpenFileDialog` con filtro según el año:

- **Año ≥ 2017:** solo `*.xlsm` (formato oficial con macros).
- **Años anteriores:** `*.xls`, `*.xlsx`, `*.xlsm`.

En modo múltiple (`CargaDatosMultiple.ProcesaArchivo`, líneas 1771–1805) se itera una carpeta de archivos
y se aplica el mismo criterio; los archivos que no cumplen se marcan "Ignorado" en la grilla de resultados.

### 4.2 Snapshot de procesos Excel

`GetAllExcelProcesses` (2169–2193) guarda los PID de los procesos `EXCEL` activos antes de comenzar.
Al terminar, `KillAllAddedExcelProcesses` mata solo los que aparecieron durante la carga
(limpieza defensiva de instancias huérfanas de Excel).

### 4.3 Extracción del encabezado — hoja "NOMBRE" (`Carga_Nombre`, líneas 2546–2586)

El workbook se abre con `WorkbookFactory.Create` (NPOI detecta automáticamente el formato) y se leen
**celdas fijas** de la hoja **"NOMBRE"**:

| Celdas | Dato extraído | Ejemplo |
|---|---|---|
| `C3 + D3 + E3 + F3 + G3 + H3` | ID del establecimiento (concatenado) | `1070170101010` |
| `C6 + D6` | Mes (número) | `3` |
| `B7` | Año | `2026` |
| `B17` (primeros 7–8 caracteres) | Serie | `SERIE A` |
| `A9` (primeros 12 caracteres) | Versión del formato | `Versión 1.2` |

Cada celda se lee con `DevuelveTextoNPOI` → **`DevuelveValorNPOI`** (líneas 3141–3201), que:

- Obtiene `sheet.GetRow(fila).GetCell(col)`.
- **Evalúa fórmulas in-place** (`HSSFFormulaEvaluator` para .xls, `XSSFFormulaEvaluator` para .xlsx/.xlsm).
- Resuelve el tipo de celda: String (con trim), Numeric (respeta formato de fecha si aplica,
  sino `NumericCellValue`), Boolean, Error (`FormulaError`), Blank → 0.

### 4.4 Validaciones previas (contra Global.mdb y Trans.mdb)

En `Button2_Click` / `CargaExcel` (líneas 1807–1846) se valida:

1. **Establecimiento pertenece al servicio de salud configurado** — `comprueba_estab` consulta
   `Global.mdb → mpEstablecimiento` filtrando por el `idservicio` guardado en
   `Trans.mdb → SetServicio`. Detalle histórico: desde 2014 se usan códigos **sin guion**;
   antes de 2014, códigos **con guion** (`like '%-%'`).
2. **Mes texto vs. número** — `bases.compara_mes` (bases.cs 119–137): "ENERO"↔01, "FEBRERO"↔02, … "DICIEMBRE"↔12.
3. **Año y serie** del archivo coinciden con los seleccionados en pantalla.
4. **Versión del formato** — `verifica_version` consulta `Global.mdb → version` por
   `ano + mes + SerieExcel` (ej.: Serie interna "SERIE BM1" ↔ SerieExcel "SERIE BM").
   Si no coincide, el archivo se rechaza.

### 4.5 Descubrimiento de hojas y rangos — `Busca_Hoja` (líneas 2475–2544)

Consulta `Global.mdb → Thojas`:

```sql
Select Hoja, rango from Thojas where ano = <año> and Serie = '<serie interna>'
```

→ obtiene la **lista de hojas** del Excel y el **rango de celdas** de cada una (ej.: hoja `A01`,
rango `A3:L35`). El sistema no "adivina" dónde están los datos: el posicionamiento lo define el
Minsal centralmente en el catálogo. Antes de iterar, `busca_version` mapea la serie del Excel
(`SerieExcel`) a la serie interna (`Serie`).

### 4.6 Volcado del rango a memoria — `llenaDatasetNPOI` (líneas 3203–3238)

`CellRangeAddress.ValueOf(rango)` → itera filas y columnas del rango y arma un `DataSet` llamando
a `DevuelveValorNPOI` celda a celda. El DataSet se asigna a un `DataGridView` temporal
(`DataGridView3`) que actúa como **buffer en memoria de toda la hoja**.

### 4.7 Validación fila a fila de prestaciones — `Busca_Dato` (líneas 2270–2473)

Consulta `Global.mdb → Diccionario`:

```sql
Select codigoprestacion, linea, inicio, fin
from Diccionario where Year = <año> and Serie = '<serie>' and hoja = '<hoja>'
```

Para **cada prestación** el diccionario indica en qué **línea** del Excel está y qué **columnas**
ocupa (`inicio`→`fin`). Luego, celda por celda:

**a) Excepciones de celdas:**
- `VeriExcep` (2119–2132): tabla `Excep` (por hoja/serie/año) → posiciones `colexcep` que se
  deben **ignorar** por prestación (se tratan como DBNull).
- `busca_col` (2151–2167): tabla `colexcep` → columnas a omitir por `codpres`.

**b) Reglas numéricas** (función `Cuantas` cuenta puntos decimales):

| Condición | Resultado |
|---|---|
| Valor no numérico o negativo | **Error** (va al log) |
| Más de un punto decimal | **Error** (corta el análisis de la fila) |
| Exactamente un decimal | Error **salvo excepciones**: hoja `A09`, hoja `A21`, `SERIE BS` y `SERIE D` (ahí se acepta) |
| Sin decimales (entero) | Válido |

**c) Resultado por fila:**

- **Fila válida** → grilla `DataGridView1` ("Datos correctos"): mes, idservicio, año,
  establecimiento, código de prestación + valores formateados con `FormatNumber`
  (separador decimal normalizado a `.`).
- **Fila inválida** → grilla `DataGridView2` ("Log de errores"): establecimiento, mes, serie,
  línea, hoja y `"columna: valor"` del dato que falló.

**d) Hojas de control** (nombre de hoja con ≥ 6 caracteres, ej. `A01/RESUMEN`):
van a `Busca_DatoContro` (líneas 2041–2112) y llenan la grilla `DataGridView4`
("Datos de control"). En años ≥ 2014, el IdServicio de la fila se toma de `BuscaServicio`
(`Trans.mdb → SetServicio`); antes se derivaba del propio código del establecimiento.

> En modo múltiple, `ProcesaArchivo` filtra por extensión/año, y los errores de todos los archivos
> se acumulan además en la tabla `INCORRECTO` de `TemporalIncorrectos.mdb` (ver §5.3).

---

## 5. Flujo de guardado en las bases .mdb

### 5.1 Creación de bases por plantilla — `bases.cs` (verificado completo, 138 líneas)

El corazón del almacenamiento es el módulo `bases` (líneas 11–23): **las bases operativas se crean
copiando plantillas**, no con DDL:

```csharp
crea_base("Datos" + serie + año)        → File.Copy("MasterDB.mdb", "Datos<serie><año>.mdb", overwrite: true)
crea_baseControl("Control" + serie + año) → File.Copy("MasterC.mdb", "Control<serie><año>.mdb", overwrite: true)
```

- **`MasterDB.mdb`** = plantilla de bases de datos (tabla `Datos` con Col01–Col50).
- **`MasterC.mdb`** = plantilla de bases de control (tabla `Datos` con col01–col05).

DDL (`CREATE TABLE`) solo se usa para bases **temporales de informes**:

- `crea_TablaInforme1` → crea `TemporalInforme1.mdb` con tabla `INF1` (columnas dinámicas).
- `crea_TablaRegistrosIncorrectosMultiple` → crea `TemporalIncorrectos.mdb` con tabla `INCORRECTO`
  (archivo, idestablecimiento, mes, serie, línea, hoja, dato, año).
  (Ambas borran y regeneran el archivo si ya existía.)

**Convención de nombres de las bases operativas** (serie sin espacios):

```
DatosSERIEA2026.mdb    ControlSERIEA2026.mdb
DatosSERIEBS2026.mdb  ControlSERIEBS2026.mdb
DatosSERIEBM2026.mdb  ControlSERIEBM2026.mdb
DatosSERIED2026.mdb    ControlSERIED2026.mdb
DatosSERIEP2026.mdb   ControlSERIEP2026.mdb
DatosSERIEF2026.mdb    ControlSERIEF2026.mdb
```

### 5.2 Guardar la carga individual — `CargaDatos.Button4_Click` (líneas 2785–3104)

1. Si no existen → `bases.crea_base` / `bases.crea_baseControl` para la serie/año elegida.
2. `busca_estable`: verifica en **`Trans.mdb → HistCarga`** si ese establecimiento + mes + año + serie
   ya fue cargado.
3. **Caso "nueva carga":**
   - `INSERT INTO HistCarga` (marca el registro como cargado).
   - Recorre la grilla fila a fila armando `Insert into Datos values(...)` **por concatenación de
     strings**: `mes, idservicio, año, establecimiento, código prestación, fechaIngreso (Now), Col01…ColNN`.
   - **Filas donde todas las columnas de valor son 0 o Null se descartan** (contador interno de
     valores ≠ 0); los vacíos se insertan como `Null`.
   - La grilla de control se inserta igual (tabla `Datos` del `Control<SERIE><AÑO>.mdb`).
   - Los errores van a **`Trans.mdb → LogError`**.
   - Confirma con MsgBox "Los Datos Fueron Ingresados Satisfactoriamente".
4. **Caso "ya cargado":** pregunta si desea actualizar → `DELETE FROM Datos WHERE IdEstablecimiento=…
   AND mes=… AND ano=…` (en la base de datos y, si existe, en la de control vía `busca_estableControl`),
   `DELETE LogError` → y re-inserta todo.
5. Al cerrar el formulario se cierran las conexiones (`cnGlobal`, `cnTrans`, …).

### 5.3 Guardar la carga masiva — `CargaDatosMultiple.CargaABase` (líneas 2330–2644)

Por cada archivo válido de la carpeta, el mismo patrón que §5.2, con estas diferencias:

- El log de incorrectos de **todos** los archivos se inserta en
  **`TemporalIncorrectos.mdb → INCORRECTO`** (incluye el nombre del archivo de origen).
- El resultado por archivo queda visible en la grilla `dgvArchivos` (OK / Ejecutado con error / Ignorado).

### 5.4 Borrado de datos — `Eliminar` + `bases`

- `bases.borra_datos` (bases.cs 73–84): `DELETE FROM datos WHERE idestablecimiento=… AND mes=… AND ano=…`
  en el `DatosSERIE<año>.mdb` correspondiente (crea la base si no existe).
- `bases.Elimina_Historico` (86–97): borra de **`Trans.mdb`** los registros de `HistCarga` y `LogError`
  del establecimiento/mes/año/serie.

---

## 6. Mapa completo de todas las bases de datos (esquema verificado)

> Todas las tablas y columnas listadas a continuación fueron **verificadas directamente contra los
> archivos .mdb reales** vía `Microsoft.ACE.OLEDB.12.0` (GetOleDbSchemaTable), no inferidas.

### 6.1 Bases maestras y catálogo

#### `Global.mdb` (418 MB) — "el cerebro" del validador

| Tabla | Columnas | Rol |
|---|---|---|
| `Diccionario` | CodigoPrestacion, Mes, Year, TextoPrestacion, Hoja, Serie, Posicion, TipoDato, Linea, Inicio, Fin | Posiciona cada prestación: en qué hoja, línea y columnas (inicio→fin) está en el Excel |
| `Thojas` | Serie, Hoja, ano, mes, Rango | Hojas del Excel y rango de celdas a leer, por serie/año |
| `Excep` | CodigoPrestacion, Mes, Year, Hoja, Serie, Posicion, tipoDato, colexcep | Celdas que se ignoran por prestación/hoja/serie (ej.: código 01010001, hoja B, SERIE BS, col 37) |
| `colexcep` | codpres, col | Columnas a omitir por código de prestación |
| `mpEstablecimiento` | IdEstablecimiento, NEstablecimiento, Direccion, Telefono, Fax, IdServicio, IdComuna, IdDependencia, IdTipo, ElimID, Propiedad | Maestro de establecimientos (se filtra por ElimID=0/null) |
| `mpServicio` | idservicio, nservicio, idregion, orden, elimId | Maestro de servicios de salud (llena el combo de `Seteo`) |
| `Meses` | nummes, mes | 1=Enero … 12=Diciembre (llena combos) |
| `Software` | Nombre, Version | Versión local de la BD (CargaRem 2.00.416) — se compara con el web service |
| `version` | mes, ano, version, Serie, SerieExcel | Versión del formato Excel esperada por mes/año/serie; mapea SerieExcel ↔ Serie interna |

#### Plantillas (no contienen datos de usuario)

| Archivo | Tablas | Rol |
|---|---|---|
| `MasterDB.mdb` | `Datos` (Mes, IdServicio, Ano, IdEstablecimiento, CodigoPrestacion, fechaIngreso, **Col01…Col50**) | **Plantilla** de todos los `DatosSERIE<AÑO>.mdb` |
| `MasterC.mdb` | `Datos` (mismo encabezado + **col01…col05**) | **Plantilla** de todos los `ControlSERIE<AÑO>.mdb` (solo 5 valores: hojas de control/resumen) |
| `MASTER.mdb` | `Datos` (56 col.), `DatosC`, `Registros` (CantReg, idservicio, Serie, Mes, Fecha, ano) | **Plantilla del archivo de carga al Minsal** (ver §7) |

#### `Trans.mdb` — estado transaccional local

| Tabla | Columnas | Rol |
|---|---|---|
| `HistCarga` | IdEstablecimiento, Mes, Ano, Serie | Histórico de cargas (evita duplicar; consulta `busca_estable`) |
| `LogError` | idestablecimiento, mes, serie, linea, hoja, dato, ano | Log de errores de validación por establecimiento |
| `SetServicio` | idServicio, Nservicio | **El servicio de salud configurado** (una sola fila; se escribe una única vez desde `Seteo`) |

### 6.2 Bases operativas (una por serie × año) — se crean al necesitarlas

| Patrón | Tabla | Cómo se llena |
|---|---|---|
| `DatosSERIE<X><AÑO>.mdb` (X = A, BS, BM, D, P, F) | `Datos` (Col01–Col50) | Cargas locales desde Excel (§5.2) **o** descarga del servidor (§8.2) |
| `ControlSERIE<X><AÑO>.mdb` | `Datos` (col01–col05) | Hojas de control de los Excel (nombre de hoja ≥ 6 caracteres) |

Ejemplos reales en la instalación: `DatosSERIEA2026.mdb` (97 MB), `DatosSERIEP2025.mdb` (30 MB),
`DatosSERIEBS2025.mdb` (27 MB), etc. Las series/año no trabajadas (ej.: `DatosSERIEF2025.mdb`, 200 KB)
quedan como plantilla recién copiada, sin datos.

### 6.3 Bases temporales (regeneradas en cada informe)

| Archivo | Tabla | Generado por |
|---|---|---|
| `Temporal.mdb` | `Cargados` (Idestablecimiento, Serie, Ene…Dic) | `EstabCargados`: matriz establecimiento × meses con "X", consultando los 6 `DatosSERIE<AÑO>` del año contra la lista de `mpEstablecimiento` del servicio. Se borra (`Delete * from cargados`) y regenera en cada ejecución |
| `TemporalControl.mdb` | `Error` | `InformeControl`: establecimientos con datos de control faltantes (cruce `mpEstablecimiento` × `ControlSERIE<AÑO>`) |
| `TemporalIncorrectos.mdb` | `INCORRECTO` (archivo, idestablecimiento, mes, serie, linea, hoja, dato, ano) + `Datos` (vacía, de la plantilla) | Carga múltiple: errores de todos los archivos procesados; consultado por `LogErrorMultiples` (exportable a Excel) |
| `TemporalInforme1.mdb` (se crea al vuelo) | `INF1` (mes, codigo, glosa + columnas dinámicas) | `Informe1` — `crea_TablaInforme1` con CREATE TABLE según columnas de la serie |

### 6.4 Residuos (no referenciados por el código actual)

- **`Control.mdb`** (tabla `Datos`): no aparece referenciado en el código decompilado (verificado por
  búsqueda textual + grafo). Reliquia de una versión/serie antigua.
- **`DatosSERIE*2012.mdb`** (200 KB = tamaño de plantilla): sin uso actual.
- `connectionString` "Rem.mdb" (Jet 4.0) del `app.config`: resto del diseñador original, **sin uso**.
- El directorio `dnSpy\` es la herramienta decompiladora, ajena al sistema.

---

## 7. Generación del archivo de carga al Minsal

Módulo **`Minsal.cs`** (título del formulario: generación del archivo que se sube al portal):

1. **`Minsal_Load`** (668–738): lee el servicio configurado de `Trans.mdb → SetServicio`; llena el combo
   de años desde `Global.mdb → Diccionario` (`Select Distinct year`); preselecciona el mes actual.
2. **`Button1_Click`** (750–855), paso a paso:
   1. Verifica que exista `DatosSERIE<AÑO>.mdb` (si no, avisa "No Existen datos para este año y serie").
   2. Si falta, crea el `ControlSERIE<AÑO>.mdb` desde la plantilla.
   3. El usuario elige la ruta destino (SaveFileDialog `.mdb`).
   4. **Copia `MASTER.mdb`** como archivo base.
   5. **INSERT masivo entre bases Access** (SQL con fuente externa):
      ```sql
      INSERT INTO [Datos]
        SELECT * FROM [MS Access;DATABASE=…DatosSERIE<AÑO>.mdb;].[Datos] WHERE ano = <año>;

      INSERT INTO [DatosC]
        SELECT * FROM [MS Access;DATABASE=…ControlSERIE<AÑO>.mdb;].[Datos] WHERE ano = <año>;
      ```
   6. Inserta la fila "sobre del envío" en **`Registros`**: cantidad de registros, idservicio,
      serie, mes límite de carga, fecha de generación, año.
   7. **Comprime con GZip** (`CompressByte`) → `<nombre>.zip` y **borra el .mdb intermedio**.
   8. Ese `.zip` es el archivo que se sube manualmente al portal `cargasrem.minsal.cl`.

---

## 8. Mantención de las bases: servicios web y optimización

### 8.1 Control de versiones al inicio — `Principal_Load` (1170–1268)

1. **Versión del ejecutable**: consulta `versionejecutable.asmx` (`VersionEjecutable.VersionEjec()`).
   Si difiere de la del ensamblado → **bloquea los menús** (Datos, Informes, Bases de Datos,
   Actualización) hasta actualizar el .exe.
2. **Versión de la BD**: consulta `WebService1.version()` y la compara con `Global.mdb → Software`.
   Si difiere → ofrece abrir el módulo `Actualizar`.
3. Verifica que exista servicio configurado (`Trans.mdb → SetServicio`); si no, bloquea los menús
   y exige pasar por "Servicio de Salud → Seleccionar S.S." (`Seteo`).

### 8.2 Actualización de catálogos — `Actualizador` / `Actualizacion`

- **`Actualizacion`** (primera instalación, muestra el form `primera`): descarga desde
  `webservicedb.asmx` los catálogos completos y los inserta en `Global.mdb`
  (borra la tabla y reinserta fila a fila):
  - `datos()` → `mpEstablecimiento` (reemplaza apóstrofes por "´" para no romper el SQL)
  - `servicio()` → `mpServicio`
  - `diccionario()` → `Diccionario`
  - `Excep()` → `Excep`; `ColExcep()` → `colexcep`; `Thoja()` → `Thojas`
  - Finalmente actualiza `Software` con la versión del servidor.
- **`Actualizador`** (actualizaciones periódicas): primero consulta `Optimiza_Up.Datos_Up()`
  (tabla de tareas: Id 1=establecimientos, 2=servicios, 3=diccionario, … Excep/ColExcep/Thoja…)
  y ejecuta solo las tablas marcadas, con barras de progreso por tabla y global.

### 8.3 Descarga del histórico del servicio — `ActDBServ` ("Datos desde Servidor 3.01.000")

Por serie y año elegidos (Button1_Click, 294–492):

1. Pide confirmación ("Se Borraran todos los Datos…").
2. Crea `DatosSERIE<AÑO>.mdb` / `ControlSERIE<AÑO>.mdb` si faltan.
3. Lee el `idservicio` de `Trans.mdb → SetServicio`.
4. **Descarga** vía `CargaSerieServ.DatosSerie(serie, idservicio, año)` y, si año ≥ 2012,
   `CargaControlServ.DatosControl(serie, idservicio, año)`.
5. **Limpia** lo local: `DELETE FROM datos WHERE Ano=…` (datos y control),
   `DELETE FROM LogError WHERE Ano=… AND Serie=…`, `DELETE FROM HistCarga WHERE Ano=… AND Serie=…`
   (todo en `Trans.mdb`).
6. **Reinserta** el histórico descargado: fila a fila con Col01–Col50 (datos) y Col01–Col05 (control),
   normalizando comas a puntos (`Replace(",", ".")`).
7. **Reconstruye `HistCarga`**: `SELECT idestablecimiento, mes, ano FROM datos GROUP BY …` →
   un INSERT por grupo en `Trans.mdb`.
8. Errores de red/específicos quedan en `DetalleExcepcion.txt`.

### 8.4 Compactación — `Optimiza` (líneas 209–232)

Recorre **todos** los `*.mdb` del directorio de instalación y para cada uno:

```csharp
jetEngine.CompactDatabase("…Data Source=<archivo>", "…Data Source=~<archivo>;Jet OLEDB:Engine Type=5");
File.Delete(<archivo>);  File.Move("~<archivo>", <archivo>);
```

(Es la razón de existencia de `Interop.JRO.dll`. Necesario porque la app inserta fila a fila sin
transacciones, lo que hace crecer el archivo .mdb; por eso `Global.mdb` llega a 418 MB y las
bases de datos a decenas/cientos de MB.)

### 8.5 Otros consumidores de las bases (informes/auditoría)

| Módulo | Bases que usa | Qué hace |
|---|---|---|
| `EstabCargados` | `Temporal.mdb`, los 6 `DatosSERIE<AÑO>.mdb`, `Trans.mdb`, `Global.mdb` | Matriz de establecimientos × meses cargados (por serie) |
| `InformeControl` | `TemporalControl.mdb`, `ControlSERIE<AÑO>.mdb`, `Trans.mdb`, `Global.mdb` | Establecimientos sin datos de control |
| `LogErrorCargados` | `Trans.mdb → LogError`, `Global.mdb` | Consulta del log de errores |
| `LogErrorMultiples` | `TemporalIncorrectos.mdb → INCORRECTO` | Errores de la carga masiva (exportable a Excel) |
| `Comparativos` / `ComparativoMes` | `Global.mdb` + `DatosSERIE` | Cruces entre establecimientos / entre meses |
| `Duplicidad` | `DatosSERIE<AÑO>.mdb`, `Global.mdb` | Detección de duplicados |
| `Prestacion`, `Decimales`, `ValoryText` | `DatosSERIE`, `Global.mdb` | Auditorías específicas (prestaciones, decimales, textos en valores) |
| `Export` | `Global.mdb`, `DatosSERIE<AÑO>.mdb` | Exporta datos a Excel |
| `Seteo` | `Global.mdb → mpServicio`, `Trans.mdb → SetServicio` | Selección única e irreversible del servicio de salud |

---

## 9. Observaciones técnicas y riesgos detectados

1. **SQL construido por concatenación en toda la aplicación** (`"Insert into Datos values(" + … + ")"`):
   - Riesgo de SQL "roto" ante valores con apóstrofes o comas decimales (mitigado parcialmente con
     `Replace(",", ".")` en valores y `Replace("'", "´")` en textos descargados del servidor).
   - Sin parámetros ni transacciones.
2. **Un `ExecuteNonQuery` por fila** (sin batching): explica el crecimiento de los .mdb y la necesidad
   del módulo `Optimiza` (compactación manual).
3. **La grilla intermedia como buffer**: todo el Excel pasa por `DataGridView`s en memoria con
   `Application.DoEvents` para refrescar la UI — los archivos grandes son lentos y la UI puede
   quedar "congelada" visualmente durante la carga.
4. **Reglas de decimales especiales**: solo hoja `A09`, hoja `A21`, `SERIE BS` y `SERIE D` aceptan
   un decimal; el resto de hojas exige enteros (más de un decimal siempre se rechaza).
5. **Seguridad de transporte**: endpoints SOAP de 2015 en HTTP/HTTPS sin firma de datos; el .zip
   de carga no está cifrado (GZip es compresión, no cifrado).
6. **Doble fuente de verdad para "versión"**: la versión del Excel se valida contra
   `Global.mdb → version`, que a su vez se sincroniza desde el servidor; si el catálogo local
   está desactualizado, se rechazan archivos válidos (la app avisa con el módulo `Actualizador`).
7. **Detalle del decompilado**: en `CargaDatosMultiple.CargaABase` el decompiler muestra `"\\\\"`
   (doble backslash) en las rutas; en el binario original VB es un `"\"` simple — es un artefacto
   de la decompilación, no un bug del sistema.
8. **Archivos huérfanos**: plantillas sin uso (`Control.mdb`, `DatosSERIE*2012.mdb`) y el folder
   `dnSpy\` ocupan espacio sin ser referenciados por el código actual.

---

## Anexo A: módulos del sistema y su rol

| Archivo (decompilado) | Rol |
|---|---|
| `Principal.cs` | Menú principal; control de versiones (exe y BD); exige servicio configurado |
| `CargaDatos.cs` | Carga individual de un Excel REM: lectura, validación y guardado |
| `CargaDatosMultiple.cs` | Carga masiva de una carpeta de Excels |
| `bases.cs` | Creación de bases por plantilla, tablas temporales, borrados, utilidades (compara_mes) |
| `Minsal.cs` | Generación del archivo .mdb/.zip de carga al portal (plantilla MASTER.mdb) |
| `EstabCargados.cs` | Informe matriz establecimientos × meses cargados |
| `InformeControl.cs` | Informe de establecimientos sin datos de control |
| `Informe1.cs` | Informe de datos con tabla INF1 dinámica |
| `LogErrorCargados.cs` / `LogErrorMultiples.cs` | Consulta/exportación de logs de errores |
| `Comparativos.cs` / `ComparativoMes.cs` | Comparativos entre establecimientos / meses |
| `Duplicidad.cs` | Detección de duplicados |
| `Prestacion.cs`, `Decimales.cs`, `ValoryText.cs` | Auditorías específicas de datos |
| `Eliminar.cs` | Borrado de datos e histórico |
| `Export.cs` | Exportación de datos a Excel |
| `Seteo.cs` | Selección del servicio de salud (una sola vez) |
| `Actualizacion.cs` / `Actualizador.cs` | Primera carga / actualización de catálogos desde el servidor |
| `ActDBServ.cs` | Descarga del histórico de datos del servicio por serie/año |
| `Optimiza.cs` | Compactación de todos los .mdb (JRO JetEngine) |
| `primera.cs`, `Cargando.cs`, `buscaActualizacion.cs` | Formularios auxiliares (splash/progreso) |
| `Datos/WebServiceDB.cs` y demás proxies | Clientes SOAP de los servicios del Minsal |

## Anexo B: llamadores confirmados con el grafo

Consulta Cypher ejecutada contra el índice (`MATCH (c)-[:CALLS]->(m) WHERE m.name IN [...]`):

| Función (`bases`) | Llamadores confirmados |
|---|---|
| `crea_base` | `CargaDatos.Button4_Click`, `CargaDatosMultiple.CargaABase`, `EstabCargados.Inserta_datosTemp`, `EstabCargados.Inserta_datosTemp_total`, `ActDBServ.Button1_Click`, `bases.crea_TablaInforme1`, `bases.crea_TablaRegistrosIncorrectosMultiple`, `bases.borra_datos`, `Informe1.inserta_datosInf1` |
| `crea_baseControl` | `CargaDatos.Button4_Click`, `CargaDatosMultiple.CargaABase`, `Minsal.Button1_Click`, `ActDBServ.Button1_Click`, `InformeControl.Button1_Click` |
| `borra_datos` | `Eliminar.Bt_borrar_Click` |
| `Elimina_Historico` | `Eliminar.Bt_borrar_Click` |

## Anexo C: flujo end-to-end resumido

```
Excel REM (.xlsm) del establecimiento
   │
   │ [NPOI] hoja "NOMBRE": establecimiento (C3:H3), mes (C6+D6), año (B7), serie (B17), versión (A9)
   │
   │ [Global.mdb] mpEstablecimiento → ¿el establecimiento es del servicio configurado (Trans.mdb)?
   │ [Global.mdb] version          → ¿la versión del Excel es la esperada para mes/año/serie?
   │ [Global.mdb] Thojas           → qué hojas leer y con qué rango de celdas
   │ [Global.mdb] Diccionario       → línea y columnas de cada prestación dentro de la hoja
   │ [Global.mdb] Excep / colexcep  → celdas que se ignoran
   │
   │ Busca_Dato / Busca_DatoContro: validación numérica (negativos, decimales, no-numéricos)
   ▼
Grillas en memoria (correctos / control / errores)
   │
   │ [bases.crea_base / crea_baseControl] File.Copy de MasterDB.mdb / MasterC.mdb
   │ INSERT fila a fila → DatosSERIE<AÑO>.mdb (Col01–Col50) y ControlSERIE<AÑO>.mdb (col01–col05)
   │ HistCarga → Trans.mdb │ LogError → Trans.mdb │ INCORRECTO → TemporalIncorrectos.mdb
   ▼
[Minsal] MASTER.mdb + INSERT…SELECT entre .mdb + Registros → GZip → <archivo>.zip
   → subida manual al portal cargasrem.minsal.cl
   │
   ▼ (sentido inverso)
[Actualizador] catálogos desde webservicedb.asmx → Global.mdb
[ActDBServ]   histórico desde CargaSerieServ/CargaControlServ → DatosSERIE/ControlSERIE + Trans.mdb
[Optimiza]    JetEngine.CompactDatabase sobre todos los *.mdb
```

---

*Informe generado por análisis estático con decompilación ILSpy + índice de grafo codebase-memory-mcp
(1.971 nodos / 8.799 aristas, modo full) + verificación de esquema vía OLEDB ACE 12.0.
Análisis de solo lectura: no se modificó ningún componente del sistema.*
