<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Resources\Batches\BatchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewBatch extends ViewRecord
{
    protected static string $resource = BatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BatchResource::zipAction(),
            BatchResource::errorsAction(),
            Action::make('source')
                ->label('ملف المشاركين')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(fn () => route('files.batch.source', $this->record))
                ->visible(fn () => filled($this->record->source_path)),
            BatchResource::startAction(),
            BatchResource::resendFailedAction(),
            BatchResource::regenerateFailedAction(),
            BatchResource::cancelAction(),
        ];
    }
}
