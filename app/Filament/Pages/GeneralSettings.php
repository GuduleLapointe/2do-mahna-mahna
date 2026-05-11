<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
// use Filament\Schemas\Components\Group;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

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

    public function save()
    {
        $data = $this->form->getState();

        app(\App\Settings\GeneralSettings::class)->update($data);

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
        return true; // Keep until fixed,easier to comment/uncomment than retype every time
        return auth()->user()->isAdmin(); // Still not working
    }

    // public static function canEdit(): bool
    // {
    //     return true;
    //     // return auth()->user()->isAdmin();
    // }
}
