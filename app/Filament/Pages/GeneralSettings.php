<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
// use Filament\Schemas\Components\Group;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @extends Page<PageConfiguration>
 */
class GeneralSettings extends Page
{
    protected string $view = "filament.pages.general-settings";

    protected static string $settings = GeneralSettings::class;

    public function form(Schema $schema): Schema
    {
        $tzOptions = array_combine(
            timezone_identifiers_list(),
            timezone_identifiers_list(),
        );

        return $schema
            ->extraAttributes(["class" => "settings-form general-settings"])
            ->components([
                TextInput::make("app_name")
                    ->label(__("Application Name"))
                    ->dehydrated(false)
                    ->inlineLabel(),
                Select::make("timezone")
                    ->label(__("Timezone"))
                    ->options($tzOptions)
                    ->searchable()
                    ->inlineLabel(),
                Toggle::make("site_active")
                    ->label(__("Site Active"))
                    ->inlineLabel(),
            ]);

        // return $schema->components([
        //     TextInput::make("app_name")
        //         ->label(__("Application Name"))
        //         ->required(),
        // ]);
    }

    /**
     * @return void
     */
    public function save(): void
    {
        $data = $this->form->getState();

        app(\App\Settings\GeneralSettings::class)->fill($data)->save();

        Notification::make()->title(__("Settings saved"))->success()->send();
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return "heroicon-o-cog-6-tooth";
    }

    public static function getNavigationLabel(): string
    {
        return __("General Settings");
    }

    public function getTitle(): string|Htmlable
    {
        return __("General Settings");
    }

    public static function canAccess(): bool
    {
        // TODO: fix access control
        return auth()->user()->id === 1; // Keep until fixed, easier to comment/uncomment than retype every time
        // return auth()->user()->can("manage settings");
        // return auth()->user()->is_admin; // Still not working
        // return auth()->user()->isAdmin(); // Still not working, returns false
    }

    // public static function canEdit(): bool
    // {
    //     return true;
    //     // return auth()->user()->isAdmin();
    // }
}
