<x-filament-panels::page>
    <form wire:submit="save" class="aksi-stack">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit">Simpan</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
