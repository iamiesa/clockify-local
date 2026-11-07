<div>
    <h1 class="text-2xl text-ink mb-6">Log in</h1>
    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="label" for="email">Email</label>
            <input id="email" type="email" wire:model="email" class="input" autocomplete="email" autofocus>
            @error('email')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input id="password" type="password" wire:model="password" class="input" autocomplete="current-password">
            @error('password')<p class="error">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" wire:model="remember" class="accent-primary"> Stay logged in</label>
        <button class="btn-primary w-full" wire:loading.attr="disabled">Log in</button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">Don't have an account? <a href="{{ route('register') }}" class="btn-link">Sign up</a></p>
</div>
