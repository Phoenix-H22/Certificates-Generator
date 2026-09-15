<x-filament-panels::page>
    <form wire:submit="issue" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
