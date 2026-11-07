<div class="max-w-3xl">
    <h1 class="page-title mb-5">Profile settings</h1>

    <form wire:submit="save" class="card mb-4 space-y-4 p-5">
        <h2 class="text-sm uppercase tracking-wide text-muted">Profile</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Name</label><input wire:model="name" class="input" autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Email</label><input type="email" wire:model="email" class="input" autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        </div>
        <div>
            <span class="label">Avatar color</span>
            <div class="flex flex-wrap gap-2">
                @foreach ($colors as $c)
                    <button type="button" wire:key="av-{{ $c }}" wire:click="$set('avatarColor', '{{ $c }}')" aria-label="Color {{ $c }}" @class(['size-7 rounded-full cursor-pointer ring-offset-2', 'ring-2 ring-ink' => $avatarColor === $c]) style="background: {{ $c }}"></button>
                @endforeach
            </div>
        </div>

        <h2 class="pt-2 text-sm uppercase tracking-wide text-muted">Preferences</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label">Time zone</label>
                <select wire:model="timezone" class="input">@foreach ($timezones as $tz)<option value="{{ $tz }}">{{ $tz }}</option>@endforeach</select>
                @error('timezone')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Week start</label>
                <select wire:model="weekStart" class="input"><option value="1">Monday</option><option value="0">Sunday</option><option value="6">Saturday</option></select>
            </div>
            <div>
                <label class="label">Time format</label>
                <select wire:model="timeFormat" class="input"><option value="24">24-hour (13:00)</option><option value="12">12-hour (1:00 PM)</option></select>
            </div>
        </div>
        <div class="text-right"><button class="btn-primary">Save</button></div>
    </form>

    <form wire:submit="changePassword" class="card space-y-4 p-5">
        <h2 class="text-sm uppercase tracking-wide text-muted">Change password</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">Current password</label><input type="password" wire:model="currentPassword" class="input" autocomplete="current-password">@error('currentPassword')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">New password</label><input type="password" wire:model="newPassword" class="input" autocomplete="new-password">@error('newPassword')<p class="error">{{ $message }}</p>@enderror</div>
        </div>
        <div class="text-right"><button class="btn-primary">Change password</button></div>
    </form>
</div>
