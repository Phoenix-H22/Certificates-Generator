<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Batches\BatchResource;
use App\Models\Batch;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentBatches extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'آخر الدفعات';

    public function table(Table $table): Table
    {
        return $table
            ->query(Batch::query()->with('template')->latest()->limit(8))
            ->paginated(false)
            ->poll('10s')
            ->columns([
                TextColumn::make('name')->label('الدفعة')->weight('bold')->url(fn (Batch $r) => BatchResource::getUrl('view', ['record' => $r])),
                TextColumn::make('template.name')->label('القالب')->limit(30),
                TextColumn::make('status')->label('الحالة')->badge(),
                ViewColumn::make('progress')->label('التقدم')->view('filament.tables.progress'),
                TextColumn::make('total_rows')->label('الصفوف')->alignCenter(),
                TextColumn::make('created_at')->label('أُنشئت')->since(),
            ]);
    }
}
