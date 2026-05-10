@props([
    'sidebar' => false,
    'app_name' => config("app.name", "2do Mahna-Mahna"),
])

@if($sidebar)
    <flux:sidebar.brand {{ $attributes }}>
            <x-slot name="logo" class="app-logo">
            <x-app-logo-icon class="app-logo-img"  alt="{{ $app_name }}"/>
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand {{ $attributes }}>
        <x-slot name="logo" class="app-logo">
            <x-app-logo-icon class="app-logo-img"  alt="{{ $app_name }}" />
        </x-slot>
    </flux:brand>
@endif
