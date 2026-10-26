<?php

namespace App\Filament\Portal\Pages;

use BackedEnum;
use Filament\Pages\Page;

/**
 * SPECS §7.2 — Cumplimiento: matriz simple Serie × Mes (RF-007/RN-038).
 * Placeholder (FASE C); llega con T-054.
 */
class Cumplimiento extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Cumplimiento';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.portal.pages.cumplimiento';

    public function getHeading(): string
    {
        return 'Cumplimiento';
    }
}
