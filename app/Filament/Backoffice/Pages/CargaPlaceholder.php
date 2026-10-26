<?php

namespace App\Filament\Backoffice\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * Placeholder del grupo "Carga" (RN-023) — los recursos reales llegan con T-025+.
 */
class CargaPlaceholder extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static \UnitEnum|string|null $navigationGroup = 'Carga';

    protected static ?string $slug = 'carga';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.backoffice.pages.placeholder';

    public function getHeading(): string
    {
        return 'Carga y validación';
    }
}

