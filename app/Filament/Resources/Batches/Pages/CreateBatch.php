<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Certificates\Data\FieldDefinition;
use App\Certificates\Data\FieldSchema;
use App\Certificates\Pipeline\ColumnMapper;
use App\Certificates\Pipeline\SampleSheetBuilder;
use App\Certificates\Pipeline\SheetReader;
use App\Enums\BatchStatus;
use App\Enums\DeliveryChannel;
use App\Enums\FieldType;
use App\Filament\Resources\Batches\BatchResource;
use App\Jobs\ProcessBatch;
use App\Models\Template;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Five-step wizard: template → fixed values → spreadsheet → column mapping
 * → delivery. Creating the batch dispatches ProcessBatch.
 */
class CreateBatch extends CreateRecord
{
    use HasWizard;

    protected static string $resource = BatchResource::class;

    public function getSteps(): array
    {
        return [
            Step::make('القالب')
                ->icon('heroicon-o-swatch')
                ->description('اختر تصميم الشهادة')
                ->schema([
                    TextInput::make('name')
                        ->label('اسم الدفعة')
                        ->placeholder('مثال: ورشة الرقمنة — مايو 2026')
                        ->required()
                        ->maxLength(255),
                    Select::make('template_id')
                        ->label('القالب')
                        ->options(fn () => Template::active()->orderBy('name')->pluck('name', 'id'))
                        ->required()
                        ->live()
                        ->native(false)
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            // null, not []: an empty PHP array becomes a JS array in the browser
                            // and nested keys typed into it (fixed_values.event_date) are dropped.
                            $set('fixed_values', null);
                            $set('column_map', null);
                        }),
                    Placeholder::make('template_preview')
                        ->hiddenLabel()
                        ->content(function (Get $get) {
                            $template = Template::find($get('template_id'));

                            if (! $template) {
                                return '';
                            }

                            $img = $template->previewUrl() ?: $template->design->sampleImage();
                            $fields = collect($template->fields_schema->all())->map(fn (FieldDefinition $f) => $f->label.' ('.$f->scope->label().')')->implode('، ') ?: 'لا توجد حقول إضافية';

                            return new HtmlString(
                                '<img src="'.e($img).'" class="rounded-lg border max-w-md mb-2" alt="">'
                                .'<div class="text-sm text-gray-600 dark:text-gray-300">الحقول: '.e($fields).'</div>'
                                .'<a href="'.e(route('templates.preview', $template)).'" target="_blank" class="text-sm text-primary-600 underline">معاينة بالحجم الكامل</a>'
                            );
                        }),
                ]),

            Step::make('بيانات الفعالية')
                ->icon('heroicon-o-calendar-days')
                ->description('القيم الثابتة لكل الشهادات')
                ->schema([
                    Group::make()
                        ->schema(fn (Get $get) => self::fixedFieldInputs(Template::find($get('template_id')))),
                ]),

            Step::make('ملف المشاركين')
                ->icon('heroicon-o-table-cells')
                ->description('ارفع ملف Excel')
                ->schema([
                    Placeholder::make('sample_hint')
                        ->hiddenLabel()
                        ->content(fn (Get $get) => new HtmlString(
                            'الصف الأول عناوين الأعمدة (الاسم، الصفة، البريد الإلكتروني، الهاتف'.self::rowFieldLabels(Template::find($get('template_id'))).'). '
                            .'يمكنك تنزيل نموذج جاهز من الزر أدناه.'
                        )),
                    FileUpload::make('source_path')
                        ->label('ملف Excel (.xlsx)')
                        ->disk(config('certificates.disk', 'certificates'))
                        ->directory('uploads/'.now()->format('Y/m'))
                        ->visibility('private')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', '.xlsx'])
                        ->maxSize((int) config('certificates.max_upload_mb', 20) * 1024)
                        ->storeFileNamesIn('source_original_name')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state) {
                            $headers = self::headersFrom($state);
                            $set('headers', $headers);

                            $template = Template::find($get('template_id'));
                            $set('column_map', $template && $headers !== [] ? app(ColumnMapper::class)->suggest($headers, $template) : null);
                        }),
                    Hidden::make('headers')->dehydrated(false)->default([]),
                    Placeholder::make('headers_found')
                        ->label('الأعمدة الموجودة في الملف')
                        ->content(fn (Get $get) => implode(' | ', (array) $get('headers')) ?: '—')
                        ->visible(fn (Get $get) => filled($get('headers'))),
                ]),

            Step::make('ربط الأعمدة')
                ->icon('heroicon-o-arrows-right-left')
                ->description('أي عمود يذهب لأي حقل')
                ->schema([
                    Group::make()
                        ->schema(fn (Get $get) => self::columnMapInputs(Template::find($get('template_id')), (array) $get('headers'))),
                ]),

            Step::make('الإرسال')
                ->icon('heroicon-o-paper-airplane')
                ->description('كيف تصل الشهادات للمشاركين')
                ->schema([
                    CheckboxList::make('deliver_via')
                        ->label('قنوات الإرسال')
                        ->options(DeliveryChannel::class)
                        ->descriptions([
                            DeliveryChannel::Email->value => 'الشهادة مرفقة PDF مع رابط التحقق',
                            DeliveryChannel::WhatsApp->value => 'يتطلب ضبط بوابة الواتساب في ملف .env',
                        ])
                        ->default([DeliveryChannel::Email->value])
                        ->helperText('اتركها فارغة لتوليد الملفات فقط وتنزيلها كـ ZIP.'),
                    Toggle::make('start_now')
                        ->label('بدء المعالجة فور الحفظ')
                        ->default(true)
                        ->dehydrated(false),
                ]),
        ];
    }

    /* ----------------------------------------------------------------- */

    /** @return array<int, Component> */
    public static function fixedFieldInputs(?Template $template): array
    {
        if (! $template) {
            return [Placeholder::make('no_template')->hiddenLabel()->content('اختر القالب أولاً.')];
        }

        $inputs = [];

        foreach ($template->fixedFields() as $field) {
            $name = "fixed_values.{$field->key}";

            $input = match ($field->type) {
                FieldType::Date => DatePicker::make($name)->native(true)->format('Y-m-d'),
                FieldType::Number => TextInput::make($name)->numeric(),
                FieldType::Email => TextInput::make($name)->email(),
                default => TextInput::make($name)->maxLength($field->maxLength ?? 255),
            };

            $input->label($field->label)->default($field->default);

            $field->required ? $input->required() : $input->nullable();

            $inputs[] = $input;
        }

        return $inputs === []
            ? [Placeholder::make('no_fixed')->hiddenLabel()->content('هذا القالب لا يحتاج قيماً ثابتة.')]
            : [Grid::make(2)->schema($inputs)];
    }

    /**
     * @param  list<string>  $headers
     * @return array<int, Component>
     */
    public static function columnMapInputs(?Template $template, array $headers): array
    {
        if (! $template || $headers === []) {
            return [Placeholder::make('no_headers')->hiddenLabel()->content('ارفع ملف Excel أولاً.')];
        }

        $options = array_combine($headers, $headers);
        $inputs = [];

        foreach (FieldSchema::CORE_FIELDS as $key => $meta) {
            $inputs[] = Select::make("column_map.{$key}")
                ->label($meta['label'])
                ->options($options)
                ->placeholder('— غير موجود —')
                ->native(false)
                ->required($meta['required']);
        }

        foreach ($template->rowFields() as $field) {
            $inputs[] = Select::make("column_map.{$field->key}")
                ->label($field->label)
                ->options($options)
                ->placeholder('— غير موجود —')
                ->native(false)
                ->required($field->required);
        }

        return [Grid::make(2)->schema($inputs)];
    }

    private static function rowFieldLabels(?Template $template): string
    {
        if (! $template || $template->rowFields() === []) {
            return '';
        }

        return '، '.collect($template->rowFields())->map(fn (FieldDefinition $f) => $f->label)->implode('، ');
    }

    /** @return list<string> */
    private static function headersFrom(mixed $state): array
    {
        $file = is_array($state) ? reset($state) : $state;

        if (! $file instanceof TemporaryUploadedFile) {
            return [];
        }

        try {
            return app(SheetReader::class)->headers($file->getRealPath());
        } catch (Throwable) {
            return [];
        }
    }

    /* ----------------------------------------------------------------- */

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sample')
                ->label('تنزيل نموذج Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $templateId = $this->data['template_id'] ?? null;
                    $template = $templateId ? Template::find($templateId) : Template::active()->first();

                    if (! $template) {
                        return null;
                    }

                    $builder = app(SampleSheetBuilder::class);
                    $path = $builder->build($template);

                    return response()->download($path, $builder->fileName($template))->deleteFileAfterSend();
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['headers'], $data['start_now'], $data['template_preview'], $data['sample_hint'], $data['headers_found']);

        $data['status'] = BatchStatus::Draft;
        $data['deliver_via'] = array_values((array) ($data['deliver_via'] ?? []));
        $data['fixed_values'] = (array) ($data['fixed_values'] ?? []);
        $data['column_map'] = (array) ($data['column_map'] ?? []);
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        if ((bool) ($this->data['start_now'] ?? true)) {
            $this->record->markStatus(BatchStatus::Queued);
            ProcessBatch::dispatch($this->record->getKey());
        }
    }

    protected function getRedirectUrl(): string
    {
        return BatchResource::getUrl('view', ['record' => $this->record]);
    }
}
