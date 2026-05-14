<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Welcome') }} - {{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        {{-- <link rel="icon" href="/favicon.svg" type="image/svg+xml"> --}}
        {{-- <link rel="apple-touch-icon" href="/apple-touch-icon.png"> --}}

        @fonts

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
        <header class="w-full lg:max-w-4xl max-w-[335px] text-sm mb-6 not-has-[nav]:hidden">
            @if (Route::has('login'))
                <nav class="flex items-center justify-end gap-4">
                <ul class="flex gap-6">
                    @auth
                    <li>
                        <a
                            href="{{ route('dashboard') }}"
                            class="button nav-button"
                        >
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a
                            href="/admin"
                            class="button nav-button"
                        >
                            Admin
                        </a>
                    </li>
                    @else
                    <li>
                        <a
                            href="{{ route('login') }}"
                            class="button nav-button"
                        >
                            Log in
                        </a>
                    </li>

                        @if (Route::has('register'))
                    <li>
                            <a
                                href="{{ route('register') }}"
                                class="button nav-button"
                                Register
                            </a>
                    </li>
                        @endif
                    @endauth
                <ul>
                </nav>
            @endif
        </header>
        <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
            <main class="flex max-w-[335px] w-full flex-col-reverse lg:max-w-4xl lg:flex-row">
                <div class="relative" style="margin:auto;">
                    {{-- App logo --}}
                    <img src="{{ asset(config('app.logo-square')) }}" alt="{{ config('app.name') }} Logo">

                </div>
            </main>
        </div>

        @if (Route::has('login'))
            <div class="h-14.5 hidden lg:block"></div>
        @endif
    </body>
</html>
