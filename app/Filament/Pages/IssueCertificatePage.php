<?php

namespace App\Filament\Pages;

use App\Certificates\Exceptions\InvalidRowException;
use App\Certificates\Pipeline\SingleCertificateIssuer;
use App\Enums\DeliveryChannel;
use App\Enums\FieldType;
use App\Filament\Resources\Batches\Pages\CreateBatch;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Models\Template;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/** Issue one certificate by hand, without a spreadsheet. */
class IssueCertificatePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'شهادة فردية';

    protected static ?string $title = 'إصدار شهادة فردية';

    protected static string|\UnitEnum|null $navigationGroup = 'الإصدار';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'issue-certificate';

    protected string $view = 'filament.pages.issue-certificate';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'template_id' => Template::active()->orderBy('id')->value('id'),
            'deliver_via' => [DeliveryChannel::Email->value],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('القالب والفعالية')
                    ->schema([
                        Select::make('template_id')
                            ->label('القالب')
                            ->options(fn () => Template::active()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(function (Set $set) {
                                $set('fixed_values', null);
                                $set('recipient', null);
                            }),
                        Group::make()
                            ->schema(fn (Get $get) => CreateBatch::fixedFieldInputs(Template::find($get('template_id')))),
                    ]),

                Section::make('المستفيد')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('recipient.name')->label('الاسم الكامل')->required()->maxLength(255),
                            TextInput::make('recipient.title')->label('الصفة / اللقب')->placeholder('الدكتور، الأستاذة، المهندس…')->maxLength(255),
                            TextInput::make('recipient.email')->label('البريد الإلكتروني')->email()->maxLength(255),
                            TextInput::make('recipient.phone')->label('رقم الهاتف (واتساب)')->tel()->placeholder('01012345678')->maxLength(32),
                        ]),
                        Group::make()
                            ->schema(fn (Get $get) => self::rowFieldInputs(Template::find($get('template_id')))),
                    ]),

                Section::make('الإرسال')
                    ->schema([
                        CheckboxList::make('deliver_via')
                            ->label('قنوات الإرسال')
                            ->options(DeliveryChannel::class)
                            ->helperText('اتركها فارغة لتوليد الملف فقط.'),
                    ]),
            ])
            ->statePath('data');
    }

    /** @return array<int, Component> */
    public static function rowFieldInputs(?Template $template): array
    {
        if (! $template) {
            return [];
        }

        $inputs = [];

        foreach ($template->rowFields() as $field) {
            $name = "recipient.{$field->key}";

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

        return $inputs === [] ? [] : [Grid::make(2)->schema($inputs)];
    }

    public function issue(SingleCertificateIssuer $issuer): void
    {
        $state = $this->form->getState();
        $template = Template::findOrFail($state['template_id']);

        try {
            $certificate = $issuer->issue(
                template: $template,
                fixedValues: (array) ($state['fixed_values'] ?? []),
                recipient: (array) ($state['recipient'] ?? []),
                channels: array_values((array) ($state['deliver_via'] ?? [])),
                userId: auth()->id(),
            );
        } catch (InvalidRowException $e) {
            Notification::make()->danger()->title('بيانات غير مكتملة')->body($e->summary())->persistent()->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('تم بدء إصدار الشهادة')
            ->body('كود التحقق: '.$certificate->code.' — سيتم التوليد والإرسال خلال ثوانٍ.')
            ->send();

        $this->redirect(CertificateResource::getUrl('view', ['record' => $certificate]));
    }

    /** @return array<Action> */
    public function getFormActions(): array
    {
        return [
            Action::make('issue')->label('إصدار الشهادة')->icon('heroicon-o-document-check')->submit('issue'),
        ];
    }
}
