@php use App\Enums\MemberStatus; use App\Enums\Role; use App\Support\Money; $currency = $this->workspace->currency; @endphp
<div>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="page-title mr-auto">Team</h1>
        @if ($this->isAdmin && $tab === 'members')
            <button type="button" class="btn-primary" wire:click="openInvite"><x-icon name="plus" class="size-4" /> Add new member</button>
        @endif
    </div>

    <nav class="mb-4 flex border-b border-line">
        <button type="button" wire:click="$set('tab', 'members')" @class(['tab', 'tab-active' => $tab === 'members'])>Members</button>
        <button type="button" wire:click="$set('tab', 'groups')" @class(['tab', 'tab-active' => $tab === 'groups'])>Groups</button>
    </nav>

    @if ($tab === 'members')
        <div class="card mb-4 flex flex-wrap items-center gap-2 p-2">
            <select wire:model.live="status" class="input w-auto" aria-label="Status">
                <option value="active">Show active</option>
                <option value="inactive">Show inactive</option>
                <option value="all">Show all</option>
            </select>
            <div class="relative min-w-48 flex-1">
                <x-icon name="search" class="absolute left-2.5 top-2.5 size-4 text-muted" />
                <input type="text" wire:model.live.debounce.300ms="search" class="input pl-8" placeholder="Search by name or email">
            </div>
        </div>

        <div class="card overflow-x-auto">
            <div class="card-header">Members</div>
            <table class="w-full text-sm">
                <thead><tr class="table-head border-b border-line-soft text-left"><th class="px-3 py-2 sm:px-4">Name</th><th class="hidden px-4 py-2 md:table-cell">Billable rate ({{ $currency }})</th><th class="hidden px-4 py-2 md:table-cell">Role</th><th class="hidden px-4 py-2 lg:table-cell">Groups</th><th class="w-12"></th></tr></thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($members as $m)
                        @php $locked = ! $this->isAdmin || $m->role === Role::Owner || $m->user_id === auth()->id(); @endphp
                        <tr wire:key="m-{{ $m->id }}-{{ $m->updated_at?->getTimestamp() }}" class="hover:bg-canvas/60">
                            <td class="w-full max-w-0 px-3 py-2.5 sm:px-4 md:w-auto md:max-w-none">
                                <div class="flex items-center gap-3 min-w-0">
                                    <x-avatar :user="$m->user" />
                                    <div class="min-w-0">
                                        <p class="truncate text-ink">{{ $m->user->name }}
                                            @if ($m->status === MemberStatus::Invited)<span class="chip ml-1 bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Invited</span>@endif
                                            @if ($m->status === MemberStatus::Inactive)<span class="chip ml-1 bg-canvas text-muted">Inactive</span>@endif
                                        </p>
                                        <p class="truncate text-xs text-muted">{{ $m->user->email }}</p>
                                    </div>
                                </div>
                                <div class="mt-2 grid grid-cols-2 items-center gap-2 pl-11 md:hidden">
                                @if ($this->isAdmin)
                                    <input type="number" min="0" step="0.01" value="{{ Money::fromCents($m->hourly_rate) }}" placeholder="{{ Money::fromCents($this->workspace->hourly_rate) }} "
                                           x-on:change="$wire.setRate({{ $m->id }}, $event.target.value)" class="input h-8 w-full">
                                @else
                                    <span class="text-muted">{{ $m->hourly_rate !== null ? Money::format($m->hourly_rate, $currency) : '—' }}</span>
                                @endif
                                                            @if ($locked)
                                    <span class="text-muted">{{ $m->role->label() }}</span>
                                @else
                                    <select class="input h-8 w-full py-0" x-on:change="$wire.setRole({{ $m->id }}, $event.target.value)">
                                        @foreach ($roles as $role)<option value="{{ $role->value }}" @selected($m->role === $role)>{{ $role->label() }}</option>@endforeach
                                    </select>
                                @endif
                                                            </div>
                            </td>
                            <td class="hidden px-4 py-2.5 md:table-cell">
                                @if ($this->isAdmin)
                                    <input type="number" min="0" step="0.01" value="{{ Money::fromCents($m->hourly_rate) }}" placeholder="{{ Money::fromCents($this->workspace->hourly_rate) }} (default)"
                                           x-on:change="$wire.setRate({{ $m->id }}, $event.target.value)" class="input h-8 w-36">
                                @else
                                    <span class="text-muted">{{ $m->hourly_rate !== null ? Money::format($m->hourly_rate, $currency) : '—' }}</span>
                                @endif
                            </td>
                            <td class="hidden px-4 py-2.5 md:table-cell">
                                @if ($locked)
                                    <span class="text-muted">{{ $m->role->label() }}</span>
                                @else
                                    <select class="input h-8 w-32 py-0" x-on:change="$wire.setRole({{ $m->id }}, $event.target.value)">
                                        @foreach ($roles as $role)<option value="{{ $role->value }}" @selected($m->role === $role)>{{ $role->label() }}</option>@endforeach
                                    </select>
                                @endif
                            </td>
                            <td class="hidden px-4 py-2.5 lg:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($groupsByUser[$m->user_id] ?? [] as $g)<span class="chip bg-canvas text-ink">{{ $g->name }}</span>@empty<span class="text-muted">—</span>@endforelse
                                </div>
                            </td>
                            <td class="pr-2 text-right">
                                @if (! $locked)
                                    <div x-data="dropdown" class="relative inline-block" x-on:click.outside="close()">
                                        <button type="button" class="btn-icon" x-on:click="toggle()" aria-label="Actions"><x-icon name="dots" /></button>
                                        <div x-show="open" x-cloak class="dropdown-panel right-0 w-48 text-left">
                                            @if ($m->status === MemberStatus::Invited)
                                                <button type="button" class="dropdown-item" x-on:click="navigator.clipboard.writeText(@js(route('invitations.accept', $m->invite_token))); $store.toasts.push('Invite link copied'); close()"><x-icon name="link" class="size-4 text-muted" /> Copy invite link</button>
                                            @elseif ($m->status === MemberStatus::Active)
                                                <button type="button" class="dropdown-item" wire:click="setStatus({{ $m->id }}, 'inactive')" x-on:click="close()">Deactivate</button>
                                            @else
                                                <button type="button" class="dropdown-item" wire:click="setStatus({{ $m->id }}, 'active')" x-on:click="close()">Activate</button>
                                            @endif
                                            <button type="button" class="dropdown-item text-danger" wire:click="remove({{ $m->id }})" wire:confirm="Remove {{ $m->user->name }} from this workspace?" x-on:click="close()">Remove from workspace</button>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="team" title="No members found" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        @if ($this->isAdmin)
            <form wire:submit="createGroup" class="card mb-4 flex flex-wrap items-start gap-2 p-2">
                <div class="min-w-48 flex-1">
                    <input wire:model="newGroup" class="input" placeholder="Add new group">
                    @error('newGroup')<p class="error">{{ $message }}</p>@enderror
                </div>
                <button class="btn-primary">Add</button>
            </form>
        @endif
        <div class="card overflow-x-auto">
            <div class="card-header">Groups</div>
            <table class="w-full text-sm">
                <thead><tr class="table-head border-b border-line-soft text-left"><th class="px-4 py-2">Name</th><th class="px-4 py-2">Members</th><th class="w-24"></th></tr></thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($groups as $group)
                        <tr wire:key="g-{{ $group->id }}">
                            <td class="px-4 py-2.5">{{ $group->name }}</td>
                            <td class="px-4 py-2.5">
                                <div class="flex -space-x-1.5">
                                    @forelse ($group->users->take(8) as $u)<x-avatar :user="$u" size="size-7 text-[10px] ring-2 ring-surface" />@empty<span class="text-muted">No members</span>@endforelse
                                    @if ($group->users->count() > 8)<span class="flex size-7 items-center justify-center rounded-full bg-canvas text-[10px] ring-2 ring-surface">+{{ $group->users->count() - 8 }}</span>@endif
                                </div>
                            </td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                @if ($this->isAdmin)
                                    <button type="button" class="btn-icon" wire:click="editGroup({{ $group->id }})" title="Edit"><x-icon name="pencil" class="size-4" /></button>
                                    <button type="button" class="btn-icon hover:text-danger" wire:click="deleteGroup({{ $group->id }})" wire:confirm="Delete this group?" title="Delete"><x-icon name="trash" class="size-4" /></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><x-empty-state icon="team" title="No groups" text="Groups let you filter reports by team." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <x-modal name="invite" title="Add members">
        @if ($invited)
            <p class="mb-3 text-sm text-muted">Share these links with the invitees so they can join (email delivery isn't configured locally):</p>
            <ul class="mb-4 space-y-2">
                @foreach ($invited as $i => $inv)
                    <li wire:key="inv-{{ $i }}" class="rounded-sm border border-line-soft p-2 text-sm">
                        <p class="text-ink">{{ $inv['email'] }}</p>
                        @if ($inv['link'])
                            <div class="mt-1 flex items-center gap-2">
                                <input readonly value="{{ $inv['link'] }}" class="input h-8 text-xs" x-on:focus="$event.target.select()">
                                <button type="button" class="btn-icon" x-on:click="navigator.clipboard.writeText(@js($inv['link'])); $store.toasts.push('Copied')"><x-icon name="copy" class="size-4" /></button>
                            </div>
                        @else
                            <p class="text-xs text-muted">Already an active member.</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <form wire:submit="invite" id="invite-form" class="space-y-4">
            <div>
                <label class="label">Emails</label>
                <textarea wire:model="inviteEmails" rows="3" class="input" placeholder="Enter email addresses (separated by comma or new line)"></textarea>
                @error('inviteEmails')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Role</label>
                <select wire:model="inviteRole" class="input">
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'invite')">Close</button>
            <button type="submit" form="invite-form" class="btn-primary">Invite</button>
        </x-slot:footer>
    </x-modal>

    <x-modal name="group-form" title="Edit group">
        <form wire:submit="saveGroup" id="group-form" class="space-y-4">
            <div><label class="label">Name</label><input wire:model="groupName" class="input">@error('groupName')<p class="error">{{ $message }}</p>@enderror</div>
            <div>
                <span class="label">Members</span>
                <div class="max-h-64 overflow-y-auto rounded-sm border border-line-soft">
                    @foreach ($allMembers as $member)
                        <label wire:key="gm-{{ $member->id }}" class="dropdown-item"><input type="checkbox" value="{{ $member->id }}" wire:model="groupMembers" class="accent-primary"> {{ $member->name }}</label>
                    @endforeach
                </div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'group-form')">Cancel</button>
            <button type="submit" form="group-form" class="btn-primary">Save</button>
        </x-slot:footer>
    </x-modal>
</div>
