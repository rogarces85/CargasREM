<?php

namespace App\Filament\Backoffice\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * Placeholder del grupo "Recepción" (RN-023) — el flujo llega con T-055+.
 */
class RecepcionPlaceholder extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox';

    protected static \UnitEnum|string|null $navigationGroup = 'Recepción';

    protected static ?string $slug = 'recepcion';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.backoffice.pages.placeholder';

    public function getHeading(): string
    {
        return 'Recepción establecimientos';
    }
}

