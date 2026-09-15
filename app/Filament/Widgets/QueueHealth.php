<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tells the admin whether the queue worker is actually running. */
class QueueHealth extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        if (! Schema::hasTable('jobs')) {
            return [];
        }

        $pending = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('available_at');
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $waitingMinutes = $oldest ? (int) max(0, (now()->getTimestamp() - (int) $oldest) / 60) : 0;

        $workerStalled = $pending > 0 && $waitingMinutes >= 3;

        return [
            Stat::make('مهام في الانتظار', number_format($pending))
                ->description($workerStalled
                    ? "أقدم مهمة تنتظر منذ {$waitingMinutes} دقيقة — تأكد أن queue:work يعمل"
                    : ($pending > 0 ? 'يتم تنفيذها الآن' : 'الطابور فارغ'))
                ->icon($workerStalled ? 'heroicon-o-exclamation-circle' : 'heroicon-o-cpu-chip')
                ->color($workerStalled ? 'danger' : ($pending > 0 ? 'info' : 'success')),
            Stat::make('مهام فاشلة', number_format($failed))
                ->description($failed > 0 ? 'راجع php artisan queue:failed' : 'لا توجد مهام فاشلة')
                ->icon('heroicon-o-x-circle')
                ->color($failed > 0 ? 'warning' : 'gray'),
        ];
    }
}
