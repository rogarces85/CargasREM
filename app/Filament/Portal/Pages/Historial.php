<?php

namespace App\Filament\Portal\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * SPECS §7.2 — Historial de cargas del establecimiento (RF-016, RN-001 aislado).
 * Placeholder (FASE C); llega con T-053.
 */
class Historial extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Historial';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.portal.pages.historial';

    public function getHeading(): string
    {
        return 'Historial de cargas';
    }
}
