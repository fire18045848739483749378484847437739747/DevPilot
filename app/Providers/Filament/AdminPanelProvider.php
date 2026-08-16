<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(__('panel.brand'))
            // Tema "hacker": verde de terminal sobre fundo escuro.
            ->colors([
                'primary' => Color::hex('#22c55e'),
                'danger' => Color::hex('#ef4444'),
                'warning' => Color::hex('#f59e0b'),
                'success' => Color::hex('#22c55e'),
                'info' => Color::hex('#06b6d4'),
                'gray' => Color::Zinc,
            ])
            ->defaultThemeMode(ThemeMode::Dark)
            ->font('JetBrains Mono')
            // Sidebar recolhível no desktop e recolhida por padrão em telas médias.
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            // Sino de notificações: destino dos alertas de queda, limite de
            // reinícios, CPU/memória e health check (ver AlertManager).
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // CSS estático servido direto de /public: não depende de build do
            // Vite/npm nem de `filament:assets` — editar o arquivo já reflete.
            // O filemtime evita cache velho do navegador após alterações.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render(
                    '<link rel="stylesheet" href="{{ $href }}">',
                    ['href' => asset('css/hacker-theme.css') . '?v=' . static::themeVersion()],
                ),
            );
    }

    protected static function themeVersion(): string
    {
        $path = public_path('css/hacker-theme.css');

        return is_file($path) ? (string) filemtime($path) : '1';
    }
}
