<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Enums\DeliveryChannel;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Certificate;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewCertificate extends ViewRecord
{
    protected static string $resource = CertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('فتح PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn () => route('files.certificate.pdf', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => filled($this->record->pdf_path)),
            Action::make('verify')
                ->label('صفحة التحقق')
                ->icon('heroicon-o-qr-code')
                ->color('gray')
                ->url(fn () => $this->record->verifyUrl())
                ->openUrlInNewTab(),
            CertificateResource::resendAction(DeliveryChannel::Email),
            CertificateResource::resendAction(DeliveryChannel::WhatsApp),
            CertificateResource::regenerateAction(),
            CertificateResource::revokeAction(),
            CertificateResource::unrevokeAction(),
        ];
    }

    public function getTitle(): string
    {
        /** @var Certificate $record */
        $record = $this->record;

        return $record->recipient_name;
    }
}
