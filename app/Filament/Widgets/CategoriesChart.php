<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class CategoriesChart extends ChartWidget
{
    protected ?string $heading = "Categories";

    protected function getData(): array
    {
        return [
            "datasets" => [
                [
                    "label" => "Event Categories",
                    "data" => [10, 5, 2, 21, 32, 45, 74, 65, 45, 77, 89],
                ],
            ],
            "labels" => [
                "discussion",
                "sports",
                "live music",
                "commercial",
                "nightlife/entertainment",
                "games/contests",
                "pageants",
                "education",
                "arts and culture",
                "charity/support groups",
                "miscellaneous",
            ],
        ];
    }

    protected function getType(): string
    {
        return "bar";
    }
}
