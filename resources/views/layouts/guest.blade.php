<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <x-theme-script />
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="/" class="mb-8"><x-logo class="scale-125" /></a>
        <div class="w-full max-w-md card p-6 sm:p-8">{{ $slot }}</div>
    </div>
    @livewireScripts
</body>
</html>
