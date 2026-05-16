<?php
/**
 * Notes on database. Do not remove.
 *
 * Database settings follow core Laravel credentials structure, but limited to
 * settings that are relevant to external helpers library.
 *
 * Robust  supports only mysql or pgsql
 * OpenSim supports only sqlite, mysql or pgsql
 * Search, Events, Offline and Currency can align to OpenSim requirement.

    "sqlite" => [
        "database" => env("{$SLUG}_DB_DATABASE", database_path("{$slug}.sqlite")),
    ],

    "mysql" => [
        "driver" => "mysql",
        "host" => env("{$SLUG}_DB_HOST", "127.0.0.1"),
        "port" => env("{$SLUG}_DB_PORT", "3306"),
        "database" => env("{$SLUG}_DB_DATABASE", "{$slug}"),
        "username" => env("{$SLUG}_DB_USERNAME", "opensim"),
        "password" => env("{$SLUG}_DB_PASSWORD", ""),
    ],

    "pgsql" => [
        "driver" => "pgsql",
        "host" => env("{$SLUG}_DB_HOST", "127.0.0.1"),
        "port" => env("{$SLUG}_DB_PORT", "5432"),
        "database" => env("{$SLUG}_DB_DATABASE", "{$slug}"),
        "username" => env("{$SLUG}_DB_USERNAME", "opensim"),
        "password" => env("{$SLUG}_DB_PASSWORD", ""),
    ],
 */

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use App\Settings\HelpersSettings;
use App\Settings\DatabaseSettings;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;

use BackedEnum;
use Filament\Forms\Components\Hidden;
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
            ->schema([
                Section::make(__("Helpers"))
                    ->icon("carbon-connect")
                    ->collapsible()
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
                Section::make(__("General"))
                    ->icon("carbon-settings-adjust")
                    ->collapsible()
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
                Section::make(__("Search Engine"))
                    ->description(
                        "General search services, single or multi-grid",
                    )
                    ->icon("carbon-cics-db2-connection")
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        $this->makeCredentialsFields("search", __("Search")),
                        $this->makeCredentialsFields("events", __("Events")),
                    ]),
                Section::make(__("OpenSim"))
                    ->description(
                        "Grid-specific helpers, for Robust grid or standalone OpenSim server",
                    )
                    ->icon("carbon-cics-db2-connection")
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        $this->makeCredentialsFields("robust", __("Robust")),
                        $this->makeCredentialsFields("opensim", __("OpenSim")),
                        $this->makeCredentialsFields(
                            "offline",
                            __("Offline Messages"),
                        ),
                        $this->makeCredentialsFields(
                            "currency",
                            __("Currency"),
                        ),
                    ]),
            ]);
    }

    protected function makeCredentialsFields($slug, $label = "")
    {
        $label = $label ?: $slug;

        $drivers = [
            "default" => __("Default (app storage)"),
            "mysql" => "MySQL",
            "postgresql" => "PostgreSQL",
            "sqlite" => "SQLite",
        ];
        if ($slug !== "robust") {
            $drivers["robust"] = "-> use Robust";
        }
        if ($slug !== "opensim") {
            $drivers["opensim"] = "-> use OpenSim";
        }
        return Group::make([
            Flex::make([
                Select::make("$slug.type")
                    ->label($label)
                    ->extraAttributes([
                        "class" => "settings-database-name",
                    ])
                    ->options($drivers)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->default("default")
                    ->grow(false)
                    ->reactive()
                    ->afterStateUpdated(
                        fn($state, $set, $get) => $this->updateFields(
                            $state,
                            $set,
                            $get,
                            $slug,
                        ),
                    ),
                TextInput::make("$slug.hostname")
                    ->label("Host")
                    // ->hidden(
                    //     fn($get) => in_array($get("$slug.type"), [
                    //         "default",
                    //         "sqlite",
                    //         "",
                    //     ]),
                    // )
                    ->hidden($this->hideFor($slug, ["default", "sqlite"])),
                TextInput::make("$slug.port")
                    ->label("Port")
                    ->numeric()
                    ->hidden($this->hideFor($slug, ["default", "sqlite"])),
                TextInput::make("$slug.database")
                    ->label("Database")
                    ->grow(true)
                    ->hidden($this->hideFor($slug, ["default"])),
                TextInput::make("$slug.user")
                    ->label("User")
                    ->hidden($this->hideFor($slug, ["default", "sqlite"])),
                TextInput::make("$slug.password")
                    ->label("Password")
                    ->password()
                    ->hidden($this->hideFor($slug, ["default", "sqlite"])),
                TextInput::make("$slug.prefix")
                    ->label("Prefix")
                    ->grow(false)
                    ->hidden($this->hideFor($slug, [], ["opensim", "robust"])),
            ])
                ->columnSpanFull()
                ->from("md"),
            Hidden::make("$slug.trace")->default(
                fn($get) => $this->buildTrace($get, $slug),
            ),
        ]);
    }

    protected function hideFor(string $slug, array $types = [], $slugs = [])
    {
        $types[] = "";
        $types[] = "robust";
        $types[] = "opensim";
        return fn(
            $get,
        ) => in_array($get("$slug.type"), $types) || in_array($slug, $slugs);
        // return fn($get) => in_array($get("$slug.type"), $types);
    }

    protected function updateFields($type, $set, $get, $slug)
    {
        $trace = $get("$slug.trace") ?? [];
        if ($type === "default") {
            // Charge la config Laravel par défaut
            $defaultConfig = config("database.default");
            $set("$slug.hostname", $defaultConfig["host"] ?? null);
            $set("$slug.port", $defaultConfig["port"] ?? null);
            $set("$slug.database", $defaultConfig["database"] ?? null);
            $set("$slug.user", $defaultConfig["username"] ?? null);
            $set("$slug.password", $defaultConfig["password"] ?? null);
        } else {
            // Charge la config depuis trace
            $config = $trace[$type] ?? [];
            $set("$slug.hostname", $config["host"] ?? null);
            $set("$slug.port", $config["port"] ?? null);
            $set("$slug.database", $config["database"] ?? null);
            $set("$slug.user", $config["user"] ?? null);
            $set("$slug.password", $config["password"] ?? null);
        }
    }

    protected function buildTrace($get, $slug)
    {
        $trace = $get("$slug.trace") ?? [];
        $currentType = $get("$slug.type");

        // Met à jour la trace avec la config actuelle
        $trace["connection"] = $currentType;
        $trace[$currentType] = [
            "host" => $get("$slug.hostname"),
            "port" => $get("$slug.port"),
            "database" => $get("$slug.database"),
            "user" => $get("$slug.user"),
            "password" => $get("$slug.password"),
        ];

        return $trace;
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
