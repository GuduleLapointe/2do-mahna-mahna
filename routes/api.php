<?php

// use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LegacyAPIController;

$prefix_helpers = settings("helpers.base_helpers", "helpersz");
$prefix_currency = settings("helpers.base_currency", "currencyz");

Route::get("/api/user", function (Request $request) {
    return $request->user();
})->middleware("auth:sanctum");

// Route de statut général
Route::get("/status", function () {
    return response()->json(["status" => "OK"]);
});

/**
 * v3 API (current)
 */
Route::prefix("api/v3")->group(function () {
    // Route de statut pour le groupe v3
    Route::get("/status", function () {
        return response()->json(["status" => "OK", "group" => "v3"]);
    });
    // API v3 Events
    Route::get("/events", [LegacyAPIController::class, "events"])->name(
        "api.v3.events",
    );

    Route::get("/events/lsl", [LegacyAPIController::class, "eventsLsl"])->name(
        "api.v3.events.lsl",
    );

    Route::get("/events/json", [
        LegacyAPIController::class,
        "eventsJson",
    ])->name("api.v3.events.json");

    Route::get("/events/board.png", [
        LegacyAPIController::class,
        "eventsBoard",
    ])->name("api.v3.events.board");
});

/**
 * v2 API (legacy)
 */
Route::prefix("api/v2")->group(function () {
    Route::get("/status", function () {
        return response()->json(["status" => "OK"]);
    });

    Route::get("/events", [LegacyAPIController::class, "legacyEvents"])->name(
        "api.v2.events",
    );
});

// Legacy v2 helpers routing
Route::prefix($prefix_helpers ?? "no_helpers")->group(function () {
    Route::get("/status", function () {
        return response()->json(["status" => "OK"]);
    });

    // Legacy helper routes
    Route::get("/events.lsl2", [
        LegacyAPIController::class,
        "legacyEvents",
    ])->name("events.lsl2");

    Route::get("/events.lsl3", [
        LegacyAPIController::class,
        "legacyEvents",
    ])->name("events.lsl3");

    // Fallback
    Route::get("/events.php", [
        LegacyAPIController::class,
        "fallbackEvents",
    ])->name("events.php");

    // EOL API v1
    Route::get("/events.lsl", [LegacyAPIController::class, "eolEvents"])->name(
        "events.lsl",
    );
});

/**
 * Scrup API
 */
Route::prefix("api/v3/scrup")->group(function () {
    Route::get("/status", function () {
        return response()->json(["status" => "OK"]);
    });

    Route::get("/get-version", [
        LegacyAPIController::class,
        "scrupGetVersion",
    ])->name("api.v3.scrup.version");

    Route::post("/register/server", [
        LegacyAPIController::class,
        "scrupRegisterServer",
    ])->name("api.v3.scrup.register.server");

    Route::post("/register/script", [
        LegacyAPIController::class,
        "scrupRegisterScript",
    ])->name("api.v3.scrup.register.script");

    Route::post("/register/client", [
        LegacyAPIController::class,
        "scrupRegisterClient",
    ])->name("api.v3.scrup.register.client");
});
