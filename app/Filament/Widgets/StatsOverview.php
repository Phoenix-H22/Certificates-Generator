<?php

namespace App\Filament\Widgets;

use App\Enums\CertificateStatus;
use App\Enums\DeliveryStatus;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Template;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $issued = Certificate::where('status', CertificateStatus::Rendered->value)->count();
        $thisMonth = Batch::where('created_at', '>=', now()->startOfMonth())->count();
        $failedDeliveries = Certificate::where('updated_at', '>=', now()->subDays(30))
            ->where(fn ($q) => $q->where('email_status', DeliveryStatus::Failed->value)->orWhere('whatsapp_status', DeliveryStatus::Failed->value))
            ->count();

        return [
            Stat::make('شهادات صادرة', number_format($issued))
                ->description('إجمالي الشهادات التي تم توليدها')
                ->icon('heroicon-o-document-check')
                ->color('success'),
            Stat::make('دفعات هذا الشهر', number_format($thisMonth))
                ->description(Batch::active()->count().' قيد المعالجة الآن')
                ->icon('heroicon-o-queue-list')
                ->color('info'),
            Stat::make('قوالب نشطة', number_format(Template::active()->count()))
                ->description(Template::count().' قالب إجمالاً')
                ->icon('heroicon-o-swatch'),
            Stat::make('إرسال فاشل (30 يوم)', number_format($failedDeliveries))
                ->description('بريد أو واتساب لم يصل')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($failedDeliveries > 0 ? 'danger' : 'gray'),
        ];
    }
}
