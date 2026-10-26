<?php

namespace App\Filament\Portal\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * SPECS §7.2 — Calendario: ventanas de carga (RF-019), feriados marcados (RN-042).
 * Placeholder (FASE C); la lógica llega con T-050+.
 */
class Calendario extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Calendario';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.portal.pages.calendario';

    public function getHeading(): string
    {
        return 'Calendario de carga';
    }
}
