<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        {{-- Filament's compiled CSS does not ship spacing utilities for app views, so use inline spacing. --}}
        <div style="margin-top: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            @foreach ($this->getFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
