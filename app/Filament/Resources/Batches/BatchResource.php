<?php

namespace App\Filament\Resources\Batches;

use App\Enums\BatchStatus;
use App\Enums\CertificateStatus;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Filament\Resources\Batches\Pages\CreateBatch;
use App\Filament\Resources\Batches\Pages\ListBatches;
use App\Filament\Resources\Batches\Pages\ViewBatch;
use App\Filament\Resources\Batches\RelationManagers\CertificatesRelationManager;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Jobs\DeliverCertificate;
use App\Jobs\ProcessBatch;
use App\Models\Batch;
use App\Models\Certificate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BatchResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'الدفعات';

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'الدفعات';

    protected static string|\UnitEnum|null $navigationGroup = 'الإصدار';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(4)
                ->schema([
                    TextEntry::make('status')->label('الحالة')->badge(),
                    TextEntry::make('template.name')->label('القالب'),
                    TextEntry::make('deliver_via')->label('قنوات الإرسال')->badge()
                        ->formatStateUsing(fn (string $state) => DeliveryChannel::tryFrom($state)?->label() ?? $state)
                        ->placeholder('ملفات فقط'),
                    TextEntry::make('creator.name')->label('أنشأها')->placeholder('—'),
                    TextEntry::make('source_original_name')->label('ملف المشاركين')->placeholder('—'),
                    TextEntry::make('started_at')->label('بدأت')->dateTime('Y-m-d H:i')->placeholder('—'),
                    TextEntry::make('finished_at')->label('انتهت')->dateTime('Y-m-d H:i')->placeholder('—'),
                    TextEntry::make('error_message')->label('الخطأ')->color('danger')->visible(fn (Batch $r) => filled($r->error_message))->columnSpanFull(),
                ]),
            Section::make('الأرقام')
                ->columns(['default' => 2, 'md' => 4, 'xl' => 7])
                ->schema([
                    TextEntry::make('total_rows')->label('الصفوف'),
                    TextEntry::make('rendered_count')->label('تم توليدها')->color('success'),
                    TextEntry::make('render_failed_count')->label('فشل التوليد')->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                    TextEntry::make('email_sent_count')->label('بريد مُرسل')->color('success'),
                    TextEntry::make('email_failed_count')->label('بريد فاشل')->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                    TextEntry::make('whatsapp_sent_count')->label('واتساب مُرسل')->color('success'),
                    TextEntry::make('whatsapp_failed_count')->label('واتساب فاشل')->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                ]),
            Section::make('القيم الثابتة')
                ->schema([
                    TextEntry::make('fixed_values')
                        ->hiddenLabel()
                        ->state(fn (Batch $r) => collect($r->fixed_values)->map(fn ($v, $k) => ($r->template?->fields_schema->get($k)?->label ?? $k).': '.$v)->implode("\n"))
                        ->placeholder('—'),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll(fn () => Batch::active()->exists() ? '5s' : null)
            ->columns([
                TextColumn::make('name')->label('الدفعة')->searchable()->weight('bold')
                    ->description(fn (Batch $r) => $r->source_original_name),
                TextColumn::make('template.name')->label('القالب')->limit(28)->toggleable(),
                TextColumn::make('status')->label('الحالة')->badge(),
                ViewColumn::make('progress')->label('التقدم')->view('filament.tables.progress'),
                TextColumn::make('email_sent_count')->label('بريد')->alignCenter()->toggleable()
                    ->formatStateUsing(fn (Batch $r) => $r->deliversVia(DeliveryChannel::Email) ? $r->email_sent_count.($r->email_failed_count ? " / {$r->email_failed_count} فشل" : '') : '—'),
                TextColumn::make('whatsapp_sent_count')->label('واتساب')->alignCenter()->toggleable()
                    ->formatStateUsing(fn (Batch $r) => $r->deliversVia(DeliveryChannel::WhatsApp) ? $r->whatsapp_sent_count.($r->whatsapp_failed_count ? " / {$r->whatsapp_failed_count} فشل" : '') : '—'),
                TextColumn::make('creator.name')->label('أنشأها')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('أُنشئت')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(BatchStatus::class)->multiple(),
                SelectFilter::make('template_id')->label('القالب')->relationship('template', 'name')->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::zipAction(),
                ActionGroup::make([
                    self::errorsAction(),
                    self::startAction(),
                    self::resendFailedAction(),
                    self::regenerateFailedAction(),
                    self::cancelAction(),
                ]),
            ]);
    }

    /* ----------------------------------------------------------------- */
    /*  Actions (shared by the table and the view page) */
    /* ----------------------------------------------------------------- */

    public static function zipAction(): Action
    {
        return Action::make('zip')
            ->label('تنزيل ZIP')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('success')
            ->url(fn (Batch $r) => route('files.batch.zip', $r))
            ->visible(fn (Batch $r) => filled($r->zip_path));
    }

    public static function errorsAction(): Action
    {
        return Action::make('errors')
            ->label('تقرير الأخطاء')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('warning')
            ->url(fn (Batch $r) => route('files.batch.errors', $r))
            ->visible(fn (Batch $r) => filled($r->error_report_path));
    }

    public static function startAction(): Action
    {
        return Action::make('start')
            ->label('بدء المعالجة')
            ->icon('heroicon-o-play')
            ->color('primary')
            ->visible(fn (Batch $r) => in_array($r->status, [BatchStatus::Draft, BatchStatus::Failed], true))
            ->requiresConfirmation()
            ->action(function (Batch $record) {
                $record->markStatus(BatchStatus::Queued);
                ProcessBatch::dispatch($record->id);
                Notification::make()->success()->title('بدأت معالجة الدفعة')->send();
            });
    }

    public static function resendFailedAction(): Action
    {
        return Action::make('resend_failed')
            ->label('إعادة إرسال الفاشلة')
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (Batch $r) => $r->status->isTerminal() && ($r->email_failed_count > 0 || $r->whatsapp_failed_count > 0))
            ->requiresConfirmation()
            ->action(function (Batch $record) {
                $count = 0;

                $record->certificates()
                    ->rendered()
                    ->where(fn (Builder $q) => $q->where('email_status', DeliveryStatus::Failed->value)->orWhere('whatsapp_status', DeliveryStatus::Failed->value))
                    ->each(function (Certificate $certificate) use (&$count) {
                        $channels = [];

                        if ($certificate->email_status === DeliveryStatus::Failed) {
                            $channels[] = DeliveryChannel::Email->value;
                        }

                        if ($certificate->whatsapp_status === DeliveryStatus::Failed) {
                            $channels[] = DeliveryChannel::WhatsApp->value;
                        }

                        DeliverCertificate::dispatch($certificate->id, $channels);
                        $count++;
                    });

                Notification::make()->success()->title("تمت جدولة {$count} إعادة إرسال")->send();
            });
    }

    public static function regenerateFailedAction(): Action
    {
        return Action::make('regenerate_failed')
            ->label('إعادة توليد الفاشلة')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (Batch $r) => $r->status->isTerminal() && $r->render_failed_count > 0)
            ->requiresConfirmation()
            ->modalDescription('يُعاد توليد الشهادات التي فشل توليدها بسبب خطأ تقني. الصفوف ذات البيانات الناقصة تحتاج تصحيح الملف ورفعه في دفعة جديدة.')
            ->action(function (Batch $record) {
                $count = 0;

                $record->certificates()
                    ->where('status', CertificateStatus::RenderFailed->value)
                    ->where(fn (Builder $q) => $q->whereNull('render_error')->orWhere('render_error', 'not like', 'Row %'))
                    ->each(function (Certificate $certificate) use (&$count) {
                        CertificateResource::regenerate($certificate);
                        $count++;
                    });

                Notification::make()->success()->title("تمت جدولة إعادة توليد {$count} شهادة")->send();
            });
    }

    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('إلغاء الدفعة')
            ->icon('heroicon-o-stop-circle')
            ->color('danger')
            ->visible(fn (Batch $r) => $r->status->isActive())
            ->requiresConfirmation()
            ->modalDescription('تتوقف المهام المتبقية؛ الشهادات التي اكتمل توليدها تبقى صالحة.')
            ->action(function (Batch $record) {
                $record->busBatch()?->cancel();
                $record->markStatus(BatchStatus::Cancelled);
                Notification::make()->warning()->title('تم إلغاء الدفعة')->send();
            });
    }

    public static function getRelations(): array
    {
        return [CertificatesRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['template', 'creator']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBatches::route('/'),
            'create' => CreateBatch::route('/create'),
            'view' => ViewBatch::route('/{record}'),
        ];
    }
}
