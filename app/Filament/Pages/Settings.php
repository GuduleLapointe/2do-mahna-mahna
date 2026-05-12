<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
// use Filament\Schemas\Components\Group;
// use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

// use Filament\Pages\Page;
// use Filament\Pages\PageConfiguration;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
// use DateTimeZone;

class Settings extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public function form(Schema $schema): Schema
    {
        $tzOptions = array_combine(
            timezone_identifiers_list(),
            timezone_identifiers_list(),
        );
        return $schema
            ->extraAttributes(["class" => "settings-form general-settings"])
            ->components([
                Tabs::make("Settings")->tabs([
                    Tab::make("General")
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->schema([
                            // Section::make("Application")->schema([
                            TextInput::make("app_name")
                                ->label(__("Application Name"))
                                // ->dehydrated(false)
                                ->inlineLabel(),
                            Select::make("timezone")
                                ->label(__("Timezone"))
                                ->options($tzOptions)
                                ->searchable()
                                ->inlineLabel(),
                            Toggle::make("site_active")
                                ->label(__("Site Active"))
                                ->inlineLabel(),
                        ]),
                ]),
            ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function canEdit(): bool
    {
        return auth()->user()->isAdmin();
    }
}
