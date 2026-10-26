# WebCargaREM

Sistema Web de **Carga y Validación REM** (Reporte de Establecimientos y Municipales) para el **Servicio de Salud Osorno**, que reemplaza la aplicación de escritorio CargaRem/ValidadorRem (Access/Excel) por una plataforma web con carga estandarizada, validación automática, trazabilidad y generación del archivo de consolidado para Minsal.

> Proyecto en desarrollo (MVP dirigido por `specs/TASK.md`). La documentación funcional completa está en [`specs/SPECS.md`](specs/SPECS.md).

## Stack

| Componente | Tecnología |
|---|---|
| Backend | PHP 8.2 · Laravel 12 |
| Paneles Admin | Filament 4 · bezhansalleh/filament-shield (roles/permisos spatie) |
| Base de datos | MariaDB 10.4 / MySQL 8 (utf8mb4. Tablas legacy Access vía migración COM/ACE read-only) |
| Autenticación | Sesiones + MFA TOTP obligatorio en producción para Admin/Supervisor (`EnforceMfa`) |
| Auditoría | spatie/laravel-activitylog (`recepcion_eventos`, activity log) |
| Tests | PHPUnit / Pest-less (33 tests feature+unit al 2026-10-26) |

## Arquitectura en una mirada

- **Backoffice** (`/backoffice`): usuarios internos Admin / Supervisor / Operador. Gestión de ventanas de carga, recepciones, validación, soporte, administración.
- **Portal** (`/portal`): cuentas de establecimientos (titular/suplente). Calendario, carga de series, historial y cumplimiento.
- **Aislamiento por servicio/establecimiento**: global scope `servicioActivo()` en todo dato de negocio (RN-001/RN-010).
- **Motor de validación** (FASE C): paridad comprobable contra el ValidadorRem original, leyendo los catálogos del `Global.mdb` (`cat_diccionario`, `cat_thojas`, `cat_excep`, etc.).

## Puesta en marcha (desarrollo local)

Requisitos: PHP 8.2 con extensión COM/ACE disponible (XAMPP en Windows), MariaDB/MySQL y Composer.

```bash
composer install
cp .env.example .env          # configurar DB credenciales y REM_ADMIN_PASSWORD
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
php artisan test              # suite completa
```

Preferir `C:\Users\Lenovo\AppData\Local\Temp\opencode` para artefactos temporales en Windows (`%TEMP%` genérico no está aprobado).

### Credenciales de desarrollo (seeder)

| Usuario | Rol | Panel |
|---|---|---|
| `admin@redsaludosorno.cl` | Admin | backoffice |
| `supervisor@redsaludosorno.cl` | Supervisor | backoffice |
| `operador@redsaludosorno.cl` | Operador | backoffice |
| `titular.101123@saludaldia.cl` | Establecimiento (Hospital Base 101123) | portal |

Password: valor de `REM_ADMIN_PASSWORD` en `.env` (default dev `cambiar-ya-2026`; **rotar en producción**). Recovery de password/MFA: [`docs/OPERACION.md`](docs/OPERACION.md).

## Datos privados que **no** se publican

- `BD/` — archivos `.mdb` legacy de producción (~700 MB: `Global.mdb`, `DatosSERIE*`, `Control*`). **Excluidos del repositorio.**
- `.env` — credenciales reales. Solo se versiona `.env.example`.

## Estructura relevante

```
app/
  Auth/TOTPProvider.php          # Interfaz TOTP (implementación: PD-PLAN-01)
  Http/Middleware/EnforceMfa.php # Retiene Admin/Supervisor sin TOTP en producción
  Enums/, Models/, Filament/
database/
  migrations/                    # 22 migraciones de esquema (DAT-*)
  seeders/
    CatalogosSeeder.php          # Importa catálogos desde BD/Global.mdb (COM/ACE, resumen de conteos)
    FeriadosSeeder.php           # 51 feriados 2025–2027 (RN-042) — editable por Admin
    DatabaseSeeder.php           # Roles Shield, catálogos, series, usuarios
docs/OPERACION.md                # Recovery de accesos (LIM-021/022)
specs/SPECS.md · TASK.md         # Requisitos y plan de tareas (fases A–I)
poc/                             # Herramientas de evidencia: introspección (Global.mdb OpenSchema)
```

## Estado del proyecto

| Fase | Alcance | Estado |
|---|---|---|
| A — Base | Esquema, roles, paneles, MFA, seeders | ✅ T-001..T-026 (T-001/T-005 parcial: extensiones servidor reales) |
| C — Motor validación | Paridad con ValidadorRem | ⏳ próxima |
| D–I | Consolidación, Minsal, FTP, impl. | ⏳ |

Bloqueos externos conocidos (no frenan desarrollo): T-041 (Excels REM reales), T-063 (mes límite), T-075 (API feriados/feriadosapp prov.), T-098 (FTP), PD-PLAN-01 (librería TOTP concreta).

## Seguridad

Cualquier hallazgo de seguridad debe reportarse al equipo del S.S. Osorno (canal interno REM) — no abrir issue público.

## Licencia

Interno del Servicio de Salud Osorno.
