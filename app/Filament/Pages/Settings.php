<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use App\Settings\HelpersSettings;
use App\Settings\DatabaseSettings;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Facades\FilamentView;

class Settings extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;
    // protected static string $helpers = HelpersSettings::class;
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
                Tabs::make("Settings")
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__("Databases"))
                            ->icon("carbon-cics-db2-connection")
                            ->schema([
                                Section::make(__("Search Engine"))
                                    ->collapsible()
                                    ->description(
                                        "General search services, single or multi-grid",
                                    )
                                    ->schema([
                                        Flex::make([
                                            Select::make("search.type")
                                                ->label(__("Search"))
                                                ->extraAttributes([
                                                    "class" =>
                                                        "settings-database-name",
                                                ])
                                                ->options([
                                                    "default" => __(
                                                        "Default (app storage)",
                                                    ),
                                                    "mysql" => "MySQL",
                                                    "postgresql" =>
                                                        "PostgreSQL",
                                                    "sqlite" => "SQLite",
                                                ])
                                                ->selectablePlaceholder(false)
                                                ->required()
                                                ->default("default")
                                                ->grow(false)
                                                ->reactive(),
                                            //  NO ->inlineLabel(), NADA, STOP ADDING THAT EACH TIME
                                            TextInput::make("search.hostname")
                                                ->label("Host")
                                                ->hidden(
                                                    fn($get) => $get(
                                                        "search.type",
                                                    ) === "default",
                                                ),
                                            //  NO ->inlineLabel(), NADA, STOP ADDING THAT EACH TIME
                                            TextInput::make("search.port")
                                                ->label("Port")
                                                ->numeric()
                                                ->columns(1)
                                                ->hidden(
                                                    fn($get) => $get(
                                                        "search.type",
                                                    ) === "default" ||
                                                        $get("search.type") ===
                                                            "sqlite",
                                                ),
                                            //  NO ->inlineLabel(), NADA, STOP ADDING THAT EACH TIME
                                            TextInput::make("search.database")
                                                ->label("Database")
                                                ->hidden(
                                                    fn($get) => $get(
                                                        "search.type",
                                                    ) === "default",
                                                ),
                                            TextInput::make("search.user")
                                                ->label("User")
                                                ->hidden(
                                                    fn($get) => $get(
                                                        "search.type",
                                                    ) === "default",
                                                ),
                                            //  NO ->inlineLabel(), NADA, STOP ADDING THAT EACH TIME
                                            TextInput::make("search.password")
                                                ->label("Password")
                                                ->password()
                                                ->hidden(
                                                    fn($get) => $get(
                                                        "search.type",
                                                    ) === "default",
                                                ),
                                            //  NO ->inlineLabel(), NADA, STOP ADDING THAT EACH TIME
                                            TextInput::make(
                                                "search.prefix",
                                            )->label("Prefix"),
                                            //  NO ->inlineLabel(), NADA, STOP ADDING THAT EACH TIME
                                        ])
                                            ->columnSpanFull()
                                            ->from("md"),
                                    ]),
                                Section::make(__("OpenSim"))
                                    ->collapsible()
                                    ->description(
                                        "Grid-specific helpers, for Robust grid or standalone OpenSim server",
                                    ),
                            ]),
                        Tab::make(__("Helpers"))
                            ->icon("carbon-connect")
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
                        Tab::make(__("General"))
                            ->icon("carbon-settings-adjust")
                            ->schema([
                                TextInput::make("app_name")
                                    ->label(__("Application Name"))
                                    ->inlineLabel(), // inlineLabel here is fine
                                Select::make("timezone")
                                    ->label(__("Timezone"))
                                    ->options($tzOptions)
                                    ->searchable()
                                    ->inlineLabel(), // inlineLabel here is fine
                            ]),
                    ]),
            ]);
    }

    protected function fillForm(): void
    {
        $this->callHook("beforeFill");

        $data = $this->mutateFormDataBeforeFill(
            array_merge(
                app(GeneralSettings::class)->toArray(),
                app(HelpersSettings::class)->toArray(),
                app(DatabaseSettings::class)->toArray(),
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

            $settings_tabs = [
                app(GeneralSettings::class),
                app(HelpersSettings::class),
                app(DatabaseSettings::class),
            ];

            foreach ($settings_tabs as $settings) {
                $settings->fill(
                    array_intersect_key($data, $settings->toArray()),
                );
                $settings->save();
            }

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
