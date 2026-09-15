@php
    /** @var \App\Models\Batch $record */
    $record = $getRecord();
    $percent = $record->progressPercent();
    $colour = match (true) {
        $record->status === \App\Enums\BatchStatus::Failed, $record->status === \App\Enums\BatchStatus::Cancelled => 'bg-red-500',
        $record->hasFailures() => 'bg-amber-500',
        $percent >= 100 => 'bg-green-500',
        default => 'bg-sky-500',
    };
@endphp
<div class="w-40" title="{{ $record->rendered_count }} / {{ $record->total_rows }}">
    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
        <div class="h-2 rounded-full {{ $colour }} transition-all" style="width: {{ $percent }}%"></div>
    </div>
    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        {{ $record->rendered_count }} / {{ $record->total_rows }}
        @if ($record->render_failed_count) · <span class="text-red-600">{{ $record->render_failed_count }} فشل</span> @endif
    </div>
</div>
