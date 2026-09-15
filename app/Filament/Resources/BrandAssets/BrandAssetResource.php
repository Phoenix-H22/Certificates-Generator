<?php

namespace App\Filament\Resources\BrandAssets;

use App\Enums\BrandAssetType;
use App\Filament\Resources\BrandAssets\Pages\CreateBrandAsset;
use App\Filament\Resources\BrandAssets\Pages\EditBrandAsset;
use App\Filament\Resources\BrandAssets\Pages\ListBrandAssets;
use App\Models\BrandAsset;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BrandAssetResource extends Resource
{
    protected static ?string $model = BrandAsset::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'الشعارات والتوقيعات';

    protected static ?string $modelLabel = 'أصل';

    protected static ?string $pluralModelLabel = 'الشعارات والتوقيعات والأختام';

    protected static string|\UnitEnum|null $navigationGroup = 'الإعداد';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label('النوع')
                        ->options(BrandAssetType::class)
                        ->required()
                        ->live()
                        ->native(false),
                    TextInput::make('name')
                        ->label(fn (Get $get) => $get('type') === BrandAssetType::Signature->value ? 'اسم الموقّع' : 'الاسم')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('role')
                        ->label('صفة الموقّع (مثل: مدير المركز)')
                        ->maxLength(255)
                        ->visible(fn (Get $get) => $get('type') === BrandAssetType::Signature->value),
                    TextInput::make('width_mm')
                        ->label('العرض على الشهادة (مم)')
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(200)
                        ->helperText('يمكن تركه فارغاً لاستخدام العرض الافتراضي للتصميم.'),
                    FileUpload::make('path')
                        ->label('الصورة')
                        ->disk(BrandAsset::DISK)
                        ->directory(BrandAsset::DIRECTORY)
                        ->visibility('public')
                        ->image()
                        ->maxSize(3072)
                        ->imagePreviewHeight('140')
                        ->required()
                        ->helperText('PNG بخلفية شفافة يعطي أفضل نتيجة للتوقيعات والأختام.'),
                    FileUpload::make('path_light')
                        ->label('نسخة فاتحة (للتصميم الداكن)')
                        ->disk(BrandAsset::DISK)
                        ->directory(BrandAsset::DIRECTORY)
                        ->visibility('public')
                        ->image()
                        ->maxSize(3072)
                        ->imagePreviewHeight('140'),
                    TextInput::make('sort')->label('الترتيب')->numeric()->default(0),
                    Toggle::make('is_active')->label('مفعّل')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->defaultGroup('type')
            ->columns([
                ImageColumn::make('path')->label('')->disk(BrandAsset::DISK)->height(48)->square(),
                TextColumn::make('name')->label('الاسم')->searchable()->weight('bold'),
                TextColumn::make('role')->label('الصفة')->placeholder('—'),
                TextColumn::make('type')->label('النوع')->badge(),
                TextColumn::make('width_mm')->label('العرض (مم)')->placeholder('افتراضي')->alignCenter(),
                ToggleColumn::make('is_active')->label('مفعّل'),
            ])
            ->filters([
                SelectFilter::make('type')->label('النوع')->options(BrandAssetType::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrandAssets::route('/'),
            'create' => CreateBrandAsset::route('/create'),
            'edit' => EditBrandAsset::route('/{record}/edit'),
        ];
    }
}
