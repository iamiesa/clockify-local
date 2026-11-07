<div>
    <h1 class="text-2xl text-ink mb-1">Join {{ $membership->workspace->name }}</h1>
    <p class="text-sm text-muted mb-6">You've been invited as <strong>{{ $membership->role->label() }}</strong> ({{ $membership->user->email }}).</p>
    <form wire:submit="accept" class="space-y-4">
        @if ($membership->user->password === null)
            <div>
                <label class="label" for="name">Full name</label>
                <input id="name" wire:model="name" class="input">
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password">Choose a password</label>
                <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password">
                @error('password')<p class="error">{{ $message }}</p>@enderror
            </div>
        @elseif (auth()->id() !== $membership->user_id)
            <p class="text-sm text-muted">Log in as {{ $membership->user->email }} first, then open this link again.</p>
        @endif
        <button class="btn-primary w-full">Accept invitation</button>
    </form>
</div>
