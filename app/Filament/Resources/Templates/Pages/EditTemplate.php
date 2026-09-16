<?php

namespace App\Filament\Resources\Templates\Pages;

use App\Filament\Resources\Templates\TemplateResource;
use App\Jobs\GenerateTemplateThumbnail;
use App\Models\Template;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
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
            Action::make('refreshThumbnail')
                ->label('تحديث صورة المعاينة')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    GenerateTemplateThumbnail::dispatch($this->record);

                    Notification::make()
                        ->title('أُرسلت مهمة تحديث المعاينة')
                        ->body('حدّث الصفحة بعد قليل لترى الشكل الجديد.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        GenerateTemplateThumbnail::dispatch($this->record);
    }

    /**
     * Drop deleted-asset IDs before the form is filled. Without this a stale
     * signature/stamp ID fails Select validation ("value not in allowed
     * list") and the template can no longer be saved from the UI.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['layout_config'])) {
            if (is_string($data['layout_config'])) {
                $data['layout_config'] = json_decode($data['layout_config'], true) ?? [];
            }

            if (is_array($data['layout_config'])) {
                $data['layout_config'] = Template::pruneLayoutAssetReferences($data['layout_config']);
            }
        }

        return $data;
    }

    /**
     * Safety net for assets deleted between fill and save. Note: this runs
     * after validation, so BeforeFill above is what actually unblocks saving.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['layout_config']) && is_array($data['layout_config'])) {
            $data['layout_config'] = Template::pruneLayoutAssetReferences($data['layout_config']);
        }

        return $data;
    }
}
