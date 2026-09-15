<?php

namespace App\Filament\Resources\Certificates;

use App\Enums\CertificateStatus;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Filament\Resources\Batches\BatchResource;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Filament\Resources\Certificates\Pages\ViewCertificate;
use App\Jobs\DeliverCertificate;
use App\Jobs\RenderCertificate;
use App\Models\Certificate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;

class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'الشهادات';

    protected static ?string $modelLabel = 'شهادة';

    protected static ?string $pluralModelLabel = 'الشهادات';

    protected static string|\UnitEnum|null $navigationGroup = 'الإصدار';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'recipient_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['recipient_name', 'code', 'uuid', 'email', 'phone'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('المستفيد')
                ->columns(3)
                ->schema([
                    TextEntry::make('recipient_title')->label('الصفة')->placeholder('—'),
                    TextEntry::make('recipient_name')->label('الاسم')->weight('bold'),
                    TextEntry::make('row_number')->label('رقم الصف في الملف'),
                    TextEntry::make('email')->label('البريد الإلكتروني')->placeholder('—')->copyable(),
                    TextEntry::make('phone')->label('الهاتف')->placeholder('—')->copyable(),
                    TextEntry::make('batch.name')->label('الدفعة')->url(fn (Certificate $r) => BatchResource::getUrl('view', ['record' => $r->batch_id])),
                ]),
            Section::make('التحقق')
                ->columns(3)
                ->schema([
                    TextEntry::make('code')->label('كود التحقق')->copyable()->weight('bold'),
                    TextEntry::make('uuid')->label('رقم الشهادة (UUID)')->copyable()->columnSpan(2),
                    TextEntry::make('verify_url')->label('رابط التحقق')->state(fn (Certificate $r) => $r->verifyUrl())->url(fn (Certificate $r) => $r->verifyUrl(), true)->columnSpan(2),
                    TextEntry::make('verified_count')->label('مرات التحقق'),
                ]),
            Section::make('الحالة')
                ->columns(3)
                ->schema([
                    TextEntry::make('status')->label('التوليد')->badge(),
                    TextEntry::make('email_status')->label('البريد الإلكتروني')->badge(),
                    TextEntry::make('whatsapp_status')->label('واتساب')->badge(),
                    TextEntry::make('render_error')->label('خطأ التوليد')->placeholder('—')->columnSpanFull()->visible(fn (Certificate $r) => filled($r->render_error)),
                    TextEntry::make('email_error')->label('خطأ البريد')->placeholder('—')->visible(fn (Certificate $r) => filled($r->email_error)),
                    TextEntry::make('whatsapp_error')->label('خطأ واتساب')->placeholder('—')->visible(fn (Certificate $r) => filled($r->whatsapp_error)),
                    TextEntry::make('revoke_reason')->label('سبب الإلغاء')->visible(fn (Certificate $r) => $r->isRevoked()),
                    TextEntry::make('rendered_at')->label('تاريخ التوليد')->dateTime('Y-m-d H:i')->placeholder('—'),
                ]),
            Section::make('بيانات الشهادة')
                ->schema([
                    TextEntry::make('data')
                        ->label('')
                        ->state(fn (Certificate $r) => collect($r->data)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n"))
                        ->columnSpanFull(),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('recipient_name')->label('الاسم')->searchable()->weight('bold')
                    ->description(fn (Certificate $r) => $r->recipient_title),
                TextColumn::make('code')->label('الكود')->searchable()->copyable()->fontFamily('mono'),
                TextColumn::make('batch.name')->label('الدفعة')->limit(25)->toggleable(),
                TextColumn::make('email')->label('البريد')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('phone')->label('الهاتف')->searchable()->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                TextColumn::make('status')->label('التوليد')->badge(),
                TextColumn::make('email_status')->label('بريد')->badge(),
                TextColumn::make('whatsapp_status')->label('واتساب')->badge(),
                TextColumn::make('verified_count')->label('تحقق')->alignCenter()->toggleable(),
                TextColumn::make('created_at')->label('أُنشئت')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('التوليد')->options(CertificateStatus::class),
                SelectFilter::make('email_status')->label('البريد')->options(DeliveryStatus::class),
                SelectFilter::make('whatsapp_status')->label('واتساب')->options(DeliveryStatus::class),
                SelectFilter::make('batch_id')->label('الدفعة')->relationship('batch', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Certificate $r) => route('files.certificate.pdf', $r))
                    ->openUrlInNewTab()
                    ->visible(fn (Certificate $r) => filled($r->pdf_path)),
                ActionGroup::make([
                    ViewAction::make(),
                    self::resendAction(DeliveryChannel::Email),
                    self::resendAction(DeliveryChannel::WhatsApp),
                    self::regenerateAction(),
                    self::revokeAction(),
                    self::unrevokeAction(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('resend_email')
                        ->label('إعادة إرسال البريد')
                        ->icon('heroicon-o-envelope')
                        ->action(fn (Collection $records) => self::dispatchDeliveries($records, DeliveryChannel::Email))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('resend_whatsapp')
                        ->label('إعادة إرسال واتساب')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->action(fn (Collection $records) => self::dispatchDeliveries($records, DeliveryChannel::WhatsApp))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('regenerate')
                        ->label('إعادة التوليد')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(fn (Certificate $c) => self::regenerate($c));
                            Notification::make()->success()->title('تمت جدولة إعادة التوليد')->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function resendAction(DeliveryChannel $channel): Action
    {
        return Action::make('resend_'.$channel->value)
            ->label('إعادة إرسال '.$channel->label())
            ->icon($channel === DeliveryChannel::Email ? 'heroicon-o-envelope' : 'heroicon-o-chat-bubble-left-right')
            ->visible(fn (Certificate $r) => $r->isValid())
            ->requiresConfirmation()
            ->action(function (Certificate $record) use ($channel) {
                DeliverCertificate::dispatch($record->id, [$channel->value]);
                Notification::make()->success()->title('تمت جدولة الإرسال عبر '.$channel->label())->send();
            });
    }

    public static function regenerateAction(): Action
    {
        return Action::make('regenerate')
            ->label('إعادة التوليد')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (Certificate $r) => ! $r->isRevoked())
            ->requiresConfirmation()
            ->modalDescription('سيتم توليد ملف PDF جديد لهذه الشهادة بنفس الكود ورقم التحقق، ثم إرساله حسب قنوات الدفعة.')
            ->action(function (Certificate $record) {
                self::regenerate($record);
                Notification::make()->success()->title('تمت جدولة إعادة التوليد')->send();
            });
    }

    public static function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label('إلغاء الشهادة')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (Certificate $r) => ! $r->isRevoked())
            ->schema([
                Textarea::make('reason')->label('سبب الإلغاء')->rows(2)->maxLength(255),
            ])
            ->requiresConfirmation()
            ->modalDescription('ستظهر الشهادة كـ "ملغاة" في صفحة التحقق ويتوقف رابط التنزيل العام.')
            ->action(function (Certificate $record, array $data) {
                $record->revoke($data['reason'] ?? null);
                Notification::make()->success()->title('تم إلغاء الشهادة')->send();
            });
    }

    public static function unrevokeAction(): Action
    {
        return Action::make('unrevoke')
            ->label('إعادة تفعيل الشهادة')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Certificate $r) => $r->isRevoked())
            ->requiresConfirmation()
            ->action(function (Certificate $record) {
                $record->unrevoke();
                Notification::make()->success()->title('تمت إعادة تفعيل الشهادة')->send();
            });
    }

    /** Reset and re-run render → deliver for one certificate outside any bus batch. */
    public static function regenerate(Certificate $certificate): void
    {
        $certificate->forceFill([
            'status' => CertificateStatus::Pending,
            'pdf_path' => null,
            'render_error' => null,
            'rendered_at' => null,
        ])->save();

        Bus::chain([
            new RenderCertificate($certificate->id),
            new DeliverCertificate($certificate->id),
        ])->dispatch();
    }

    /** @param Collection<int, Certificate> $records */
    public static function dispatchDeliveries(Collection $records, DeliveryChannel $channel): void
    {
        $count = 0;

        foreach ($records as $certificate) {
            if ($certificate->isValid()) {
                DeliverCertificate::dispatch($certificate->id, [$channel->value]);
                $count++;
            }
        }

        Notification::make()->success()->title("تمت جدولة {$count} إرسال عبر {$channel->label()}")->send();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['batch']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertificates::route('/'),
            'view' => ViewCertificate::route('/{record}'),
        ];
    }
}
