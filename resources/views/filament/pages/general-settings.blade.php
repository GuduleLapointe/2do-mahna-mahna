<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <h1 class="text-2xl font-bold">
            {{ __('General Settings') }}
        </h1>

        <div class="filament-forms-field-wrapper">
            <form wire:submit="save" class="space-y-6">
                {{ $this->form }}
                
                <div class="flex items-center gap-4">
                    <x-filament::button type="submit">
                        {{ __('Save') }}
                    </x-filament::button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
