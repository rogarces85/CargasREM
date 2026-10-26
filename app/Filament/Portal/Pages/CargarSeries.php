<?php

namespace App\Filament\Portal\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * SPECS §7.2 — Carga de series: subir plantillas .xlsx/.xlsm (RF-001/RN-002).
 * Placeholder (FASE C); el flujo llega con T-051/T-052.
 */
class CargarSeries extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Cargar';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.portal.pages.cargar';

    public function getHeading(): string
    {
        return 'Carga de series';
    }
}
