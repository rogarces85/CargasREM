<?php

namespace App\Filament\Backoffice\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * Placeholder del grupo "Minsal" (RN-023) — generación .zip y sync llegan con T-065/T-075.
 */
class MinsalPlaceholder extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-americas';

    protected static \UnitEnum|string|null $navigationGroup = 'Minsal';

    protected static ?string $slug = 'minsal';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.backoffice.pages.placeholder';

    public function getHeading(): string
    {
        return 'Minsal';
    }
}

