<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use App\Settings\HelpersSettings;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;
    protected static string $helpers = HelpersSettings::class;
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
                            TextInput::make("app_name")
                                ->label(__("Application Name"))
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
                    Tab::make("Helpers")
                        ->icon(Heroicon::OutlinedCog6Tooth)
                        ->schema([
                            TextInput::make("base_helpers")
                                ->label(__("Helpers base URL"))
                                ->inlineLabel()
                                ->prefix(url("/") . "/"),
                            TextInput::make("base_currency")
                                ->label(__("Currency base URL"))
                                ->inlineLabel()
                                ->prefix(url("/") . "/"),
                        ]),
                ]),
            ]);
    }

    protected function fillForm(): void
    {
        $this->callHook("beforeFill");

        $generalSettings = app(GeneralSettings::class);
        $helpersSettings = app(HelpersSettings::class);

        $data = $this->mutateFormDataBeforeFill(
            array_merge(
                $generalSettings->toArray(),
                $helpersSettings->toArray(),
            ),
        );

        $this->form->fill($data);

        $this->callHook("afterFill");
    }

    public function save(): void
    {
        if (!$this->canEdit()) {
            return;
        }

        try {
            $this->beginDatabaseTransaction();

            $this->callHook("beforeValidate");

            $data = $this->form->getState();

            $this->callHook("afterValidate");

            $data = $this->mutateFormDataBeforeSave($data);

            $this->callHook("beforeSave");

            $generalSettings = app(GeneralSettings::class);
            $helpersSettings = app(HelpersSettings::class);

            $generalSettings->fill(
                array_intersect_key($data, $generalSettings->toArray()),
            );
            $generalSettings->save();

            $helpersSettings->fill(
                array_intersect_key($data, $helpersSettings->toArray()),
            );
            $helpersSettings->save();

            $this->callHook("afterSave");
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $this->rollBackDatabaseTransaction()
                : $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();

        $this->rememberData();

        $this->getSavedNotification()?->send();

        if ($redirectUrl = $this->getRedirectUrl()) {
            $this->redirect(
                $redirectUrl,
                navigate: FilamentView::hasSpaMode($redirectUrl),
            );
        }
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
