<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use App\Filament\Pages\Settings;
use Filament\Actions\Action;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Enums\UserMenuPosition;

use Filament\Navigation\MenuItem;
use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;
use Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id("admin")
            ->path("admin")
            ->brandLogo(asset(config("app.logo")))
            ->brandLogoHeight("2rem")
            ->favicon(asset(config("app.icon")))
            ->homeUrl("/")
            ->colors([
                "primary" => "rgb(221,43,132)",
                "secondary" => "rgb(108,46,78)",
            ])
            ->discoverResources(
                in: app_path("Filament/Resources"),
                for: "App\Filament\Resources",
            )
            ->discoverPages(
                in: app_path("Filament/Pages"),
                for: "App\Filament\Pages",
            )
            ->pages([
                // Dashboard::class,
            ])
            ->plugins([
                FilamentEditProfilePlugin::make()
                    ->slug("profile")
                    // ->setTitle("My Profile")
                    ->setIcon("heroicon-o-user")
                    ->shouldRegisterNavigation(false)
                    // ->setNavigationLabel("My Profile")
                    // ->setNavigationGroup("Group Profile"),
                    // ->setSort(10)
                    // ->canAccess(fn() => auth()->user()->id === 1)
                    // ->shouldShowEmailForm()
                    // ->shouldShowDeleteAccountForm(false)
                    // ->shouldShowSanctumTokens()
                    // ->shouldShowBrowserSessionsForm()
                    ->shouldShowAvatarForm(),
                // ->customProfileComponents([
                //     \App\Livewire\CustomProfileComponent::class,
                // ]),
            ])
            ->userMenu(position: UserMenuPosition::Sidebar)
            ->userMenuItems([
                "profile" => fn(Action $action) => $action
                    ->label(fn() => auth()->user()->name)
                    ->url(fn(): string => EditProfilePage::getUrl())
                    ->icon("heroicon-m-user-circle"),
                Action::make("repository")
                    ->url("https://github.com/GuduleLapointe/2do-mahna-mahna")
                    ->icon("heroicon-o-folder")
                    ->label(__("Repository")),
                Action::make("documentation")
                    ->url(
                        "https://github.com/GuduleLapointe/2do-mahna-mahna/wiki",
                    )
                    ->icon("heroicon-o-book-open")
                    ->label(__("Documentation")),
            ])
            ->discoverWidgets(
                in: app_path("Filament/Widgets"),
                for: "App\Filament\Widgets",
            )
            ->widgets([AccountWidget::class, FilamentInfoWidget::class])
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
            ->authMiddleware([Authenticate::class]);
    }
}
