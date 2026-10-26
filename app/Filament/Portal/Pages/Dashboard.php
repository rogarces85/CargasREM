<?php

namespace App\Filament\Portal\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * MOD-14 — Inicio del portal (placeholder hasta H2/H3: resumen de ventanas y series pendientes).
 */
class Dashboard extends BaseDashboard
{
    public function getHeading(): string
    {
        return 'Inicio';
    }
}
