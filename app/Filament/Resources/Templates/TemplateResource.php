<?php

namespace App\Filament\Resources\Templates;

use App\Certificates\Data\FieldSchema;
use App\Enums\BrandAssetType;
use App\Enums\FieldScope;
use App\Enums\FieldType;
use App\Enums\TemplateDesign;
use App\Filament\Resources\Templates\Pages\CreateTemplate;
use App\Filament\Resources\Templates\Pages\EditTemplate;
use App\Filament\Resources\Templates\Pages\ListTemplates;
use App\Models\BrandAsset;
use App\Models\Template;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'القوالب';

    protected static ?string $modelLabel = 'قالب';

    protected static ?string $pluralModelLabel = 'القوالب';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعداد';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('template')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('التصميم')
                        ->icon('heroicon-o-paint-brush')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('name')
                                    ->label('اسم القالب')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state, ?Template $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                                TextInput::make('slug')
                                    ->label('المعرّف (slug)')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash(),
                            ]),
                            Radio::make('design')
                                ->label('التصميم')
                                ->options(TemplateDesign::class)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    if ($design = TemplateDesign::tryFrom((string) $state)) {
                                        $set('layout_config.accent', $design->defaultAccent());
                                    }
                                })
                                ->columns(4)
                                ->gridDirection('row'),
                            ViewField::make('design_gallery')
                                ->label('')
                                ->view('filament.forms.design-picker')
                                ->dehydrated(false),
                            Toggle::make('is_active')->label('متاح للاستخدام')->default(true),
                        ]),

                    Tab::make('النصوص والألوان')
                        ->icon('heroicon-o-language')
                        ->schema([
                            Section::make('النصوص')
                                ->description('العناصر المتاحة: {organisation} {parent_organisation} {name} {title} {issued_at} وأي حقل من حقول القالب مثل {event_name} {event_date}')
                                ->schema([
                                    TextInput::make('layout_config.title_text')->label('عنوان الشهادة')->required()->maxLength(120),
                                    Textarea::make('layout_config.body_text')->label('نص التقديم (قبل الاسم)')->required()->rows(2),
                                    Textarea::make('layout_config.closing_text')->label('نص الختام (بعد الاسم)')->required()->rows(3),
                                ]),
                            Grid::make(3)->schema([
                                ColorPicker::make('layout_config.accent')->label('اللون الأساسي'),
                                Select::make('layout_config.name_font')
                                    ->label('خط اسم المستفيد')
                                    ->options(['Amiri' => 'Amiri (نسخ كلاسيكي)', 'Cairo' => 'Cairo (حديث)', 'Tajawal' => 'Tajawal (حديث عريض)', 'Noto Naskh Arabic' => 'Noto Naskh Arabic'])
                                    ->default('Amiri'),
                                Select::make('layout_config.numerals')
                                    ->label('الأرقام')
                                    ->options(['arabic' => 'هندية (١٢٣)', 'latin' => 'لاتينية (123)'])
                                    ->default('arabic'),
                            ]),
                        ]),

                    Tab::make('الشعارات والتوقيعات والختم')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Select::make('layout_config.logos')
                                ->label('الشعارات (بالترتيب)')
                                ->multiple()
                                ->options(fn () => BrandAsset::active()->ofType(BrandAssetType::Logo)->ordered()->pluck('name', 'id'))
                                ->getOptionLabelsUsing(fn (array $values): array => BrandAsset::ofType(BrandAssetType::Logo)->whereIn('id', $values)->get()->mapWithKeys(fn (BrandAsset $a) => [$a->id => $a->name.($a->is_active ? '' : ' (موقوف)')])->all())
                                ->helperText('حتى 4 شعارات تُعرض في أعلى الشهادة.')
                                ->maxItems(4),
                            Select::make('layout_config.signatures')
                                ->label('التوقيعات (بالترتيب، حتى 3)')
                                ->multiple()
                                ->options(fn () => BrandAsset::active()->ofType(BrandAssetType::Signature)->ordered()->get()->mapWithKeys(fn (BrandAsset $a) => [$a->id => trim(($a->role ? $a->role.' — ' : '').$a->name)]))
                                ->getOptionLabelsUsing(fn (array $values): array => BrandAsset::ofType(BrandAssetType::Signature)->whereIn('id', $values)->get()->mapWithKeys(fn (BrandAsset $a) => [$a->id => trim(($a->role ? $a->role.' — ' : '').$a->name).($a->is_active ? '' : ' (موقوف)')])->all())
                                ->maxItems(Template::MAX_SIGNATURES),
                            Section::make('الختم')
                                ->columns(4)
                                ->schema([
                                    Select::make('layout_config.stamp.asset_id')
                                        ->label('صورة الختم')
                                        ->options(fn () => BrandAsset::active()->ofType(BrandAssetType::Stamp)->ordered()->pluck('name', 'id'))
                                        ->getOptionLabelUsing(function ($value): ?string {
                                            if (! $value) {
                                                return null;
                                            }

                                            $asset = BrandAsset::ofType(BrandAssetType::Stamp)->find($value);

                                            return $asset ? $asset->name.($asset->is_active ? '' : ' (موقوف)') : null;
                                        })
                                        ->placeholder('بدون ختم')
                                        ->live(),
                                    Select::make('layout_config.stamp.position')
                                        ->label('الموضع')
                                        ->options(Template::POSITIONS)
                                        ->default('bottom-center'),
                                    TextInput::make('layout_config.stamp.width_mm')->label('العرض (مم)')->numeric()->minValue(15)->maxValue(80)->default(38),
                                    TextInput::make('layout_config.stamp.rotate')->label('الميل (درجة)')->numeric()->minValue(-45)->maxValue(45)->default(-6),
                                    TextInput::make('layout_config.stamp.opacity')->label('الشفافية (0.1 – 1)')->numeric()->minValue(0.1)->maxValue(1)->step(0.05)->default(0.9),
                                ]),
                            Section::make('رمز التحقق QR')
                                ->description('رمز QR إلزامي على كل شهادة؛ يمكن تغيير موضعه وحجمه فقط.')
                                ->columns(3)
                                ->schema([
                                    Select::make('layout_config.qr_position')->label('الموضع')->options(Template::QR_POSITIONS)->default('bottom-left'),
                                    TextInput::make('layout_config.qr_size_mm')->label('الحجم (مم)')->numeric()->minValue(18)->maxValue(40)->default(24),
                                    Select::make('layout_config.background_asset_id')
                                        ->label('صورة خلفية (اختياري)')
                                        ->options(fn () => BrandAsset::active()->ofType(BrandAssetType::Background)->ordered()->pluck('name', 'id'))
                                        ->getOptionLabelUsing(function ($value): ?string {
                                            if (! $value) {
                                                return null;
                                            }

                                            $asset = BrandAsset::ofType(BrandAssetType::Background)->find($value);

                                            return $asset ? $asset->name.($asset->is_active ? '' : ' (موقوف)') : null;
                                        })
                                        ->placeholder('بدون'),
                                ]),
                        ]),

                    Tab::make('الحقول')
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            Section::make()
                                ->description('حقول الاسم والصفة والبريد والهاتف موجودة دائماً. أضف هنا الحقول الخاصة بالفعالية: قيمة ثابتة لكل الدفعة، أو قيمة لكل صف في ملف Excel.')
                                ->schema([
                                    Repeater::make('fields_schema')
                                        ->label('')
                                        ->schema([
                                            Grid::make(4)->schema([
                                                TextInput::make('key')
                                                    ->label('المفتاح (إنجليزي)')
                                                    ->required()
                                                    ->regex('/^[a-z][a-z0-9_]{0,49}$/')
                                                    ->notIn(FieldSchema::RESERVED_KEYS)
                                                    ->helperText('مثل event_name — يُستخدم كـ {event_name} في النصوص')
                                                    ->distinct(),
                                                TextInput::make('label')->label('التسمية')->required()->maxLength(80),
                                                Select::make('type')->label('النوع')->options(FieldType::class)->default(FieldType::Text->value)->required()->native(false),
                                                Select::make('scope')->label('مصدر القيمة')->options(FieldScope::class)->default(FieldScope::Fixed->value)->required()->native(false),
                                            ]),
                                            Grid::make(4)->schema([
                                                Toggle::make('required')->label('مطلوب')->default(true),
                                                Toggle::make('show_on_verify')->label('يظهر في صفحة التحقق')->default(true),
                                                TextInput::make('max_length')->label('أقصى طول')->numeric()->minValue(1)->maxValue(500),
                                                TextInput::make('default')->label('القيمة الافتراضية')->maxLength(255),
                                            ]),
                                        ])
                                        ->itemLabel(fn (array $state): ?string => ($state['label'] ?? null) ?: ($state['key'] ?? null))
                                        ->reorderable()
                                        ->collapsible()
                                        ->collapsed()
                                        ->addActionLabel('إضافة حقل')
                                        ->defaultItems(0),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                ImageColumn::make('preview_path')->label('')->disk('public')->height(60)->width(85)
                    ->defaultImageUrl(fn (Template $r) => $r->design->sampleImage()),
                TextColumn::make('name')->label('القالب')->searchable()->weight('bold')->description(fn (Template $r) => $r->slug),
                TextColumn::make('design')->label('التصميم')->badge()->color('gray'),
                TextColumn::make('batches_count')->label('الدفعات')->counts('batches')->alignCenter(),
                TextColumn::make('certificates_count')->label('الشهادات')->counts('certificates')->alignCenter(),
                ToggleColumn::make('is_active')->label('متاح'),
                TextColumn::make('updated_at')->label('آخر تعديل')->since()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('design')->label('التصميم')->options(TemplateDesign::class),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('معاينة')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Template $r) => route('templates.preview', $r))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplates::route('/'),
            'create' => CreateTemplate::route('/create'),
            'edit' => EditTemplate::route('/{record}/edit'),
        ];
    }
}
