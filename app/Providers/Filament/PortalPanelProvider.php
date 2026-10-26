<?php

namespace App\Providers\Filament;

use App\Filament\Portal\Pages\Calendario;
use App\Filament\Portal\Pages\CargarSeries;
use App\Filament\Portal\Pages\Cumplimiento;
use App\Filament\Portal\Pages\Dashboard as PortalDashboard;
use App\Filament\Portal\Pages\Historial;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * MOD-14 / RN-016 — Portal de recepción para cuentas ROL-ESTABLECIMIENTO.
 * SPECS §7.2: sidebar fija Calendario/Cargar/Historial/Cumplimiento.
 */
class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->login()
            ->brandName('Portal REM — S.S. Osorno')
            ->colors([
                'primary' => Color::Blue,
            ])
            // SPECS §7.2: sidebar sin grupos (navegación plana, mobile-first)
            ->navigationGroups([])
            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\Filament\Portal\Resources')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\Filament\Portal\Pages')
            ->pages([
                PortalDashboard::class,
                Calendario::class,
                CargarSeries::class,
                Historial::class,
                Cumplimiento::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Portal/Widgets'), for: 'App\Filament\Portal\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
