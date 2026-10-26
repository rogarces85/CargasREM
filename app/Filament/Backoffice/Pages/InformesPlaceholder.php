<?php

namespace App\Filament\Backoffice\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * Placeholder del grupo "Informes" (RN-023) — acumulados/logs/comparativos llegan con T-060+.
 */
class InformesPlaceholder extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static \UnitEnum|string|null $navigationGroup = 'Informes';

    protected static ?string $slug = 'informes';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.backoffice.pages.placeholder';

    public function getHeading(): string
    {
        return 'Informes';
    }
}

