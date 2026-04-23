<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Filter Time Tracker') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-14">
            <div class="flex items-center gap-6">
                <span class="font-semibold text-gray-900 text-sm tracking-tight">Filter Time</span>

                <a href="{{ route('timesheet') }}"
                   class="text-sm font-medium {{ request()->routeIs('timesheet') ? 'text-blue-600' : 'text-gray-600 hover:text-gray-900' }}">
                    Time
                </a>

                @if(auth()->user()->isManager())
                <a href="#"
                   class="text-sm font-medium text-gray-600 hover:text-gray-900">
                    Reports
                </a>
                @endif

                @if(auth()->user()->isAdmin())
                <a href="#"
                   class="text-sm font-medium text-gray-600 hover:text-gray-900">
                    Admin
                </a>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('auth.logout') }}">
                    @csrf
                    <button type="submit"
                            class="text-sm text-gray-500 hover:text-gray-700">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    {{ $slot }}
</main>

@livewireScripts
</body>
</html>
