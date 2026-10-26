<?php

namespace Database\Seeders;

use App\Enums\TipoFeriado;
use App\Models\Feriado;
use Illuminate\Database\Seeder;

/**
 * T-026 — Feriados 2025–2027 precargados (RN-042, RNF-020).
 *
 * Fuente: leyes/normativa vigente (gob.cl — calendarios oficiales; BCN leyes 20.215/20.983)
 * verificada 2026-10-26. Trasladables (Ley 20.215): San Pedro y San Pablo y Encuentro de
 * Dos Mundos → lunes anterior si caen martes. Ley 20.983: feriado extra el 17-Sep cuando
 * 18/19 caen sábado/domingo (2027).
 *
 * RN-042 menciona "regionales Los Lagos": la Región de Los Lagos NO tiene feriado regional
 * legal vigente (solo proyectos en trámite, p.ej. 31-Ene Incorporación de Chiloé). El enum
 * regional_los_lagos queda listo y el listado es editable por el Admin (T-065 job anual).
 * Elecciones 2025 se marcan tipo 'otro' (feriado legal administrativo).
 */
class FeriadosSeeder extends Seeder
{
    /** @var array<int, array{f: string, t: TipoFeriado, d: string}> */
    private const FERIADOS = [
        // ── 2025 ──
        ['f' => '2025-01-01', 't' => TipoFeriado::Nacional, 'd' => 'Año Nuevo'],
        ['f' => '2025-04-18', 't' => TipoFeriado::Nacional, 'd' => 'Viernes Santo'],
        ['f' => '2025-04-19', 't' => TipoFeriado::Nacional, 'd' => 'Sábado Santo'],
        ['f' => '2025-05-01', 't' => TipoFeriado::Nacional, 'd' => 'Día Nacional del Trabajo'],
        ['f' => '2025-05-21', 't' => TipoFeriado::Nacional, 'd' => 'Día de las Glorias Navales'],
        ['f' => '2025-06-20', 't' => TipoFeriado::Nacional, 'd' => 'Día Nacional de los Pueblos Indígenas'],
        ['f' => '2025-06-29', 't' => TipoFeriado::Nacional, 'd' => 'San Pedro y San Pablo'],
        ['f' => '2025-07-16', 't' => TipoFeriado::Nacional, 'd' => 'Día de la Virgen del Carmen'],
        ['f' => '2025-08-15', 't' => TipoFeriado::Nacional, 'd' => 'Asunción de la Virgen'],
        ['f' => '2025-09-18', 't' => TipoFeriado::Nacional, 'd' => 'Independencia Nacional'],
        ['f' => '2025-09-19', 't' => TipoFeriado::Nacional, 'd' => 'Día de las Glorias del Ejército'],
        ['f' => '2025-10-12', 't' => TipoFeriado::Nacional, 'd' => 'Día del Encuentro de Dos Mundos'],
        ['f' => '2025-10-31', 't' => TipoFeriado::Nacional, 'd' => 'Día Nacional de las Iglesias Evangélicas y Protestantes'],
        ['f' => '2025-11-01', 't' => TipoFeriado::Nacional, 'd' => 'Día de Todos los Santos'],
        ['f' => '2025-11-16', 't' => TipoFeriado::Otro, 'd' => 'Elecciones Presidenciales y Parlamentarias (feriado legal)'],
        ['f' => '2025-12-08', 't' => TipoFeriado::Nacional, 'd' => 'Inmaculada Concepción de la Virgen'],
        ['f' => '2025-12-14', 't' => TipoFeriado::Otro, 'd' => 'Segunda Vuelta Elecciones Presidenciales (feriado legal)'],
        ['f' => '2025-12-25', 't' => TipoFeriado::Nacional, 'd' => 'Natividad del Señor (Navidad)'],

        // ── 2026 ──
        ['f' => '2026-01-01', 't' => TipoFeriado::Nacional, 'd' => 'Año Nuevo'],
        ['f' => '2026-04-03', 't' => TipoFeriado::Nacional, 'd' => 'Viernes Santo'],
        ['f' => '2026-04-04', 't' => TipoFeriado::Nacional, 'd' => 'Sábado Santo'],
        ['f' => '2026-05-01', 't' => TipoFeriado::Nacional, 'd' => 'Día Nacional del Trabajo'],
        ['f' => '2026-05-21', 't' => TipoFeriado::Nacional, 'd' => 'Día de las Glorias Navales'],
        ['f' => '2026-06-21', 't' => TipoFeriado::Nacional, 'd' => 'Día Nacional de los Pueblos Indígenas'],
        ['f' => '2026-06-29', 't' => TipoFeriado::Nacional, 'd' => 'San Pedro y San Pablo'],
        ['f' => '2026-07-16', 't' => TipoFeriado::Nacional, 'd' => 'Día de la Virgen del Carmen'],
        ['f' => '2026-08-15', 't' => TipoFeriado::Nacional, 'd' => 'Asunción de la Virgen'],
        ['f' => '2026-09-18', 't' => TipoFeriado::Nacional, 'd' => 'Independencia Nacional'],
        ['f' => '2026-09-19', 't' => TipoFeriado::Nacional, 'd' => 'Día de las Glorias del Ejército'],
        ['f' => '2026-10-12', 't' => TipoFeriado::Nacional, 'd' => 'Día del Encuentro de Dos Mundos'],
        ['f' => '2026-10-31', 't' => TipoFeriado::Nacional, 'd' => 'Día Nacional de las Iglesias Evangélicas y Protestantes'],
        ['f' => '2026-11-01', 't' => TipoFeriado::Nacional, 'd' => 'Día de Todos los Santos'],
        ['f' => '2026-12-08', 't' => TipoFeriado::Nacional, 'd' => 'Inmaculada Concepción de la Virgen'],
        ['f' => '2026-12-25', 't' => TipoFeriado::Nacional, 'd' => 'Natividad del Señor (Navidad)'],

        // ── 2027 (traslados Ley 20.215; feriado extra Ley 20.983) ──
        ['f' => '2027-01-01', 't' => TipoFeriado::Nacional, 'd' => 'Año Nuevo'],
        ['f' => '2027-03-26', 't' => TipoFeriado::Nacional, 'd' => 'Viernes Santo'],
        ['f' => '2027-03-27', 't' => TipoFeriado::Nacional, 'd' => 'Sábado Santo'],
        ['f' => '2027-05-01', 't' => TipoFeriado::Nacional, 'd' => 'Día Nacional del Trabajo'],
        ['f' => '2027-05-21', 't' => TipoFeriado::Nacional, 'd' => 'Día de las Glorias Navales'],
        ['f' => '2027-06-07', 't' => TipoFeriado::Nacional, 'd' => 'Día Nacional de los Pueblos Indígenas'],
        ['f' => '2027-06-28', 't' => TipoFeriado::Nacional, 'd' => 'San Pedro y San Pablo (trasladado al lunes, Ley 20.215)'],
        ['f' => '2027-07-16', 't' => TipoFeriado::Nacional, 'd' => 'Día de la Virgen del Carmen'],
        ['f' => '2027-08-15', 't' => TipoFeriado::Nacional, 'd' => 'Asunción de la Virgen'],
        ['f' => '2027-09-17', 't' => TipoFeriado::Nacional, 'd' => 'Feriado Fiestas Patrias (Ley 20.983: 18/19 caen sábado/domingo)'],
        ['f' => '2027-09-18', 't' => TipoFeriado::Nacional, 'd' => 'Independencia Nacional'],
        ['f' => '2027-09-19', 't' => TipoFeriado::Nacional, 'd' => 'Día de las Glorias del Ejército'],
        ['f' => '2027-10-11', 't' => TipoFeriado::Nacional, 'd' => 'Día del Encuentro de Dos Mundos (trasladado al lunes, Ley 20.215)'],
        ['f' => '2027-10-31', 't' => TipoFeriado::Nacional, 'd' => 'Día Nacional de las Iglesias Evangélicas y Protestantes'],
        ['f' => '2027-11-01', 't' => TipoFeriado::Nacional, 'd' => 'Día de Todos los Santos'],
        ['f' => '2027-12-08', 't' => TipoFeriado::Nacional, 'd' => 'Inmaculada Concepción de la Virgen'],
        ['f' => '2027-12-25', 't' => TipoFeriado::Nacional, 'd' => 'Natividad del Señor (Navidad)'],
    ];

    public function run(): void
    {
        foreach (self::FERIADOS as $feriado) {
            Feriado::updateOrCreate(
                ['fecha' => $feriado['f']],
                ['tipo' => $feriado['t'], 'descripcion' => $feriado['d'], 'anio' => (int) substr($feriado['f'], 0, 4)],
            );
        }

        /** @disregard P1013 optional chaining of command */
        $this->command?->info('T-026 feriados: ' . Feriado::count() . ' precargados (2025: '
            . Feriado::delAnio(2025)->count() . ' / 2026: ' . Feriado::delAnio(2026)->count()
            . ' / 2027: ' . Feriado::delAnio(2027)->count() . ')');
    }
}
