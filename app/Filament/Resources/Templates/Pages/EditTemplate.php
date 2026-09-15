<?php

namespace App\Filament\Resources\Templates\Pages;

use App\Filament\Resources\Templates\TemplateResource;
use App\Jobs\GenerateTemplateThumbnail;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTemplate extends EditRecord
{
    protected static string $resource = TemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('معاينة')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => route('templates.preview', $this->record))
                ->openUrlInNewTab(),
            Action::make('previewPdf')
                ->label('معاينة PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('templates.preview', ['template' => $this->record, 'pdf' => 1]))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        GenerateTemplateThumbnail::dispatch($this->record);
    }
}
