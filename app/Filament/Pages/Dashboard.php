<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\QueueHealth;
use App\Filament\Widgets\RecentBatches;
use App\Filament\Widgets\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'لوحة التحكم';

    protected static ?string $navigationLabel = 'لوحة التحكم';

    /** @return array<class-string> */
    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            QueueHealth::class,
            RecentBatches::class,
        ];
    }
}
