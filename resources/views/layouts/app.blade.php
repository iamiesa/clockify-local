@php
    $user = auth()->user();
    $workspace = $user->currentWorkspace;
    $isAdmin = $user->isAdminOf($workspace);
    $nav = [
        ['route' => 'tracker', 'label' => 'Time Tracker', 'icon' => 'clock'],
        ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'divider' => 'Analyze'],
        ['route' => 'reports', 'label' => 'Reports', 'icon' => 'reports', 'match' => 'reports*'],
        ['route' => 'projects.index', 'label' => 'Projects', 'icon' => 'projects', 'match' => 'projects.*', 'divider' => 'Manage'],
        ['route' => 'team', 'label' => 'Team', 'icon' => 'team'],
        ['route' => 'clients', 'label' => 'Clients', 'icon' => 'clients'],
        ['route' => 'tags', 'label' => 'Tags', 'icon' => 'tag'],
    ];
    if ($isAdmin) {
        $nav[] = ['route' => 'workspace.settings', 'label' => 'Settings', 'icon' => 'settings', 'divider' => 'Workspace'];
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <x-theme-script />
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased" x-data="{ nav: false }">
    {{-- Top bar --}}
    <header class="fixed inset-x-0 top-0 z-30 flex h-14 items-center gap-2 border-b border-line bg-surface px-3 sm:px-4">
        <button type="button" class="btn-icon lg:hidden" x-on:click="nav = !nav" aria-label="Toggle navigation"><x-icon name="menu" /></button>
        <a href="{{ route('tracker') }}" wire:navigate class="mr-auto min-w-0"><x-logo class="[&>span:last-child]:hidden sm:[&>span:last-child]:inline" /></a>

        <livewire:running-timer-badge />

        <div x-data="dropdown" class="relative" x-on:click.outside="close()">
            <button type="button" x-on:click="toggle()" class="flex max-w-40 sm:max-w-60 items-center gap-1 rounded-sm px-2 h-9 text-sm hover:bg-canvas cursor-pointer">
                <x-icon name="workspace" class="size-4 text-muted shrink-0" />
                <span class="truncate">{{ $workspace->name }}</span>
                <x-icon name="chevron-down" class="size-3.5 text-muted shrink-0" />
            </button>
            <div x-show="open" x-cloak x-transition class="dropdown-panel right-0 w-64">
                <p class="px-3 pt-2 pb-1 text-xs uppercase tracking-wide text-muted">Workspaces</p>
                @foreach ($user->activeWorkspaces()->orderBy('name')->get() as $ws)
                    <form method="POST" action="{{ route('workspaces.switch', $ws) }}">
                        @csrf
                        <button class="dropdown-item justify-between">
                            <span class="truncate">{{ $ws->name }}</span>
                            @if ($ws->id === $workspace->id)<x-icon name="check" class="size-4 text-primary" />@endif
                        </button>
                    </form>
                @endforeach
                @unless (config('tracker.shared_workspace'))
                    <div class="border-t border-line-soft"><a href="{{ route('workspaces') }}" wire:navigate class="dropdown-item text-primary">Manage workspaces</a></div>
                @endunless
            </div>
        </div>

        <div x-data="dropdown" class="relative" x-on:click.outside="close()">
            <button type="button" x-on:click="toggle()" class="flex items-center rounded-full cursor-pointer" aria-label="Account menu"><x-avatar :user="$user" /></button>
            <div x-show="open" x-cloak x-transition class="dropdown-panel right-0 w-60">
                <div class="px-3 py-2 border-b border-line-soft">
                    <p class="text-sm text-ink truncate">{{ $user->name }}</p>
                    <p class="text-xs text-muted truncate">{{ $user->email }}</p>
                </div>
                <a href="{{ route('profile') }}" wire:navigate class="dropdown-item"><x-icon name="user" class="size-4 text-muted" /> Profile settings</a>
                @unless (config('tracker.shared_workspace'))
                    <a href="{{ route('workspaces') }}" wire:navigate class="dropdown-item"><x-icon name="workspace" class="size-4 text-muted" /> Workspaces</a>
                @endunless
                <div class="border-t border-line-soft px-3 py-2.5" x-data>
                    <p class="mb-1.5 text-xs uppercase tracking-wide text-muted">Theme</p>
                    <div class="grid grid-cols-3 overflow-hidden rounded-sm border border-line text-xs">
                        @foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $themeKey => $themeLabel)
                            <button type="button" x-on:click="$store.theme.set('{{ $themeKey }}')"
                                    class="flex items-center justify-center gap-1 py-1.5 cursor-pointer {{ $loop->first ? '' : 'border-l border-line' }}"
                                    x-bind:class="$store.theme.mode === '{{ $themeKey }}' ? 'bg-primary-soft text-ink font-medium' : 'text-muted hover:bg-canvas'">
                                <x-icon :name="$themeKey === 'light' ? 'sun' : ($themeKey === 'dark' ? 'moon' : 'monitor')" class="size-3.5" /> {{ $themeLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="border-t border-line-soft">
                    @csrf
                    <button class="dropdown-item"><x-icon name="logout" class="size-4 text-muted" /> Log out</button>
                </form>
            </div>
        </div>
    </header>

    {{-- Sidebar --}}
    <div x-show="nav" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/40 lg:hidden" x-on:click="nav = false"></div>
    <aside class="fixed bottom-0 left-0 top-0 z-50 w-72 max-w-[85vw] -translate-x-full overflow-y-auto border-r border-line bg-surface shadow-xl transition-transform duration-200 lg:top-14 lg:z-30 lg:w-56 lg:translate-x-0 lg:shadow-none"
           x-bind:class="nav && 'translate-x-0'" x-on:keydown.escape.window="nav = false">
        <div class="flex h-14 items-center justify-between border-b border-line-soft px-4 lg:hidden">
            <x-logo />
            <button type="button" class="btn-icon" x-on:click="nav = false" aria-label="Close navigation"><x-icon name="chevron-left" /></button>
        </div>
        <nav class="py-2">
            @foreach ($nav as $item)
                @isset($item['divider'])
                    <p class="mt-3 px-5 pb-1 pt-2 text-[11px] uppercase tracking-wider text-muted border-t border-line-soft">{{ $item['divider'] }}</p>
                @endisset
                @php $active = request()->routeIs($item['match'] ?? $item['route']); @endphp
                <a href="{{ route($item['route']) }}" wire:navigate x-on:click="nav = false"
                   @class(['flex items-center gap-3 px-5 py-3 text-sm uppercase tracking-wide transition-colors border-l-4 lg:py-2.5 lg:normal-case lg:tracking-normal', 'border-primary bg-primary-soft/60 text-ink' => $active, 'border-transparent text-ink/80 hover:bg-canvas hover:text-ink' => ! $active])>
                    <x-icon :name="$item['icon']" @class(['size-5', 'text-primary' => $active, 'text-muted' => ! $active]) />
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </aside>

    <main class="pt-14 lg:pl-56">
        <div class="mx-auto max-w-[1400px] px-3 py-5 sm:px-6 sm:py-6">
            {{ $slot }}
        </div>
    </main>

    {{-- Toasts --}}
    <div class="fixed bottom-4 right-4 z-[60] flex flex-col gap-2" x-data>
        <template x-for="toast in $store.toasts.items" :key="toast.id">
            <div x-transition class="flex min-w-64 max-w-sm items-start gap-3 rounded-sm px-4 py-3 text-sm text-white shadow-lg"
                 :class="toast.type === 'error' ? 'bg-danger' : 'bg-[#333]'">
                <span class="flex-1" x-text="toast.message"></span>
                <button type="button" x-on:click="$store.toasts.remove(toast.id)" class="opacity-70 hover:opacity-100"><x-icon name="x" class="size-4" /></button>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
