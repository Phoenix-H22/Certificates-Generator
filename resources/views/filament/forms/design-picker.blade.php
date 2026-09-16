@php
    $selected = $getState() ?? data_get($getLivewire(), 'data.design');
    // Show only the chosen design (catalog on create, preview on edit).
    $visible = collect(\App\Enums\TemplateDesign::cases())
        ->filter(fn ($design) => $design->value === $selected);
    $designs = $visible->isNotEmpty() ? $visible : collect(\App\Enums\TemplateDesign::cases());
@endphp
<div class="grid grid-cols-2 gap-4 md:grid-cols-4">
    @foreach ($designs as $design)
        <figure class="overflow-hidden rounded-lg border {{ $selected === $design->value ? 'border-primary-500 ring-2 ring-primary-500' : 'border-gray-200 dark:border-gray-700' }}">
            <img src="{{ $design->sampleImage() }}" alt="{{ $design->label() }}" class="aspect-[297/210] w-full object-cover">
            <figcaption class="px-2 py-1.5 text-xs text-gray-600 dark:text-gray-300">
                <strong class="block text-gray-900 dark:text-white">{{ $design->label() }}</strong>
                {{ $design->description() }}
            </figcaption>
        </figure>
    @endforeach
</div>
