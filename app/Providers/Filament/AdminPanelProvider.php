<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\PresupuestoPagoProveedores;
use App\Filament\Resources\SolicitudPagoResource;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Models\Menu;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigation(function (NavigationBuilder $navigation) {
                // Esta es la forma correcta de obtener el usuario autenticado
                $user = auth()->user();

                if (!$user) {
                    return $navigation;
                }

                // Obtener menús según el rol del usuario
                $menuItems = Menu::whereHas('roles', function ($query) use ($user) {
                    $query->whereIn('name', $user->roles->pluck('name'));
                })->orWhereDoesntHave('roles')->orderBy('orden')->get();

                $navigationItems = [];
                foreach ($menuItems as $menuItem) {
                    $navigationItems[] = NavigationItem::make($menuItem->nombre)
                        ->icon($menuItem->icono)
                        ->url($menuItem->ruta)
                        ->isActiveWhen(fn (): bool => request()->routeIs($menuItem->ruta));
                }

                return $navigation
                    ->items($navigationItems)
                    ->group(
                        NavigationGroup::make('Solicitudes de Pago y Aprobaciones')->items([
                            NavigationItem::make('Presupuesto de pago a proveedores')
                                ->icon('heroicon-o-clipboard-document-check')
                                ->url(fn () => PresupuestoPagoProveedores::getUrl())
                                ->isActiveWhen(fn (): bool => request()->routeIs(PresupuestoPagoProveedores::getRouteName())),
                            NavigationItem::make('Solicitudes de pago')
                                ->icon('heroicon-o-banknotes')
                                ->url(fn () => SolicitudPagoResource::getUrl())
                                ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.solicitud-pagos.*')),
                        ])
                    );
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
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
