<?php

namespace App\Filament\Resources\Batches\RelationManagers;

use App\Enums\CertificateStatus;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryStatus;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Certificate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CertificatesRelationManager extends RelationManager
{
    protected static string $relationship = 'certificates';

    protected static ?string $title = 'الشهادات';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('row_number')
            ->poll(fn () => $this->getOwnerRecord()->status->isActive() ? '5s' : null)
            ->columns([
                TextColumn::make('row_number')->label('#')->sortable(),
                TextColumn::make('recipient_name')->label('الاسم')->searchable()->weight('bold')->description(fn (Certificate $r) => $r->recipient_title),
                TextColumn::make('code')->label('الكود')->copyable()->fontFamily('mono'),
                TextColumn::make('email')->label('البريد')->placeholder('—')->searchable(),
                TextColumn::make('status')->label('التوليد')->badge(),
                TextColumn::make('email_status')->label('بريد')->badge(),
                TextColumn::make('whatsapp_status')->label('واتساب')->badge(),
                TextColumn::make('render_error')->label('الخطأ')->limit(40)->placeholder('—')->tooltip(fn (Certificate $r) => $r->render_error ?: $r->email_error ?: $r->whatsapp_error),
            ])
            ->filters([
                SelectFilter::make('status')->label('التوليد')->options(CertificateStatus::class),
                SelectFilter::make('email_status')->label('البريد')->options(DeliveryStatus::class),
                SelectFilter::make('whatsapp_status')->label('واتساب')->options(DeliveryStatus::class),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (Certificate $r) => route('files.certificate.pdf', $r))
                    ->openUrlInNewTab()
                    ->visible(fn (Certificate $r) => filled($r->pdf_path)),
                ActionGroup::make([
                    ViewAction::make()->url(fn (Certificate $r) => CertificateResource::getUrl('view', ['record' => $r])),
                    CertificateResource::resendAction(DeliveryChannel::Email),
                    CertificateResource::resendAction(DeliveryChannel::WhatsApp),
                    CertificateResource::regenerateAction(),
                    CertificateResource::revokeAction(),
                    CertificateResource::unrevokeAction(),
                ]),
            ]);
    }
}
