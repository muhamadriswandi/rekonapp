<x-filament-panels::page>
    <form wire:submit="cetakPdf" class="space-y-6">
        {{ $this->form }}
    </form>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
