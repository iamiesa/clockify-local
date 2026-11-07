<div x-init="$wire.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'">
    <h1 class="text-2xl text-ink mb-1">Sign up</h1>
    <p class="text-sm text-muted mb-6">Track time, manage projects and report on your team.</p>
    <form wire:submit="register" class="space-y-4">
        <div>
            <label class="label" for="name">Full name</label>
            <input id="name" wire:model="name" class="input" autocomplete="name" autofocus>
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" wire:model="email" class="input" autocomplete="email">
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password">
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <button class="btn-primary w-full" wire:loading.attr="disabled">Create free account</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">Already have an account? <a href="{{ route('login') }}" class="btn-link">Log in</a></p>
</div>
