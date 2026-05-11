<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Support\Enums\IconPosition;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make("Events", "192")
                ->description("32 increase")
                ->descriptionIcon(
                    "heroicon-m-arrow-trending-up",
                    IconPosition::Before,
                )
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color("success"),
            Stat::make("Grids", "32")
                ->description("7% decrease")
                ->descriptionIcon(
                    "heroicon-m-arrow-trending-down",
                    IconPosition::Before,
                )
                ->color("danger"),
            Stat::make("Teleport Boards", "217")
                ->description("3% increase")
                ->descriptionIcon(
                    "heroicon-m-arrow-trending-up",
                    IconPosition::Before,
                )
                ->color("success"),
        ];
    }
}
