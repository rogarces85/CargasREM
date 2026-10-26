<?php

namespace App\Filament\Backoffice\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * Placeholder del grupo "Administración" (RN-023) — usuarios/ventanas/series llegan con T-058+.
 */
class AdministracionPlaceholder extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static \UnitEnum|string|null $navigationGroup = 'Administración';

    protected static ?string $slug = 'administracion';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.backoffice.pages.placeholder';

    public function getHeading(): string
    {
        return 'Administración';
    }
}

