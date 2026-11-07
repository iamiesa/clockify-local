<?php

namespace App\Livewire;

use App\Actions\MemberInviteAction;
use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Membership;
use App\Models\UserGroup;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Team')]
class Team extends Component
{
    use InteractsWithWorkspace;

    #[Url(except: 'members')]
    public string $tab = 'members';

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $search = '';

    public string $inviteEmails = '';

    public string $inviteRole = 'member';

    /** @var list<array{email:string, link:?string}> */
    public array $invited = [];

    public string $newGroup = '';

    public ?int $editingGroupId = null;

    public string $groupName = '';

    /** @var list<int> */
    public array $groupMembers = [];

    /* ------------------------------------------------------------- Members */

    public function invite(MemberInviteAction $invite): void
    {
        $this->authorizeAdmin();
        $emails = collect(preg_split('/[\s,;]+/', $this->inviteEmails))->map(fn ($e) => mb_strtolower(trim($e)))->filter()->unique()->values();

        $this->validate(['inviteRole' => ['required', Rule::in([Role::Admin->value, Role::Member->value])]]);
        $invalid = $emails->reject(fn (string $e): bool => (bool) filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($emails->isEmpty() || $invalid->isNotEmpty() || $emails->count() > 50) {
            $this->addError('inviteEmails', $invalid->isNotEmpty() ? 'Invalid email: '.$invalid->implode(', ') : 'Enter between 1 and 50 email addresses.');

            return;
        }

        $this->invited = $emails->map(function (string $email) use ($invite): array {
            $membership = $invite($this->workspace, $email, Role::from($this->inviteRole));

            return ['email' => $email, 'link' => $membership->invite_token ? route('invitations.accept', $membership->invite_token) : null];
        })->all();

        $this->reset('inviteEmails');
        $this->notify(count($this->invited).' member(s) invited');
    }

    public function openInvite(): void
    {
        $this->authorizeAdmin();
        $this->reset('inviteEmails', 'inviteRole', 'invited');
        $this->resetValidation();
        $this->dispatch('open-modal', name: 'invite');
    }

    public function setRole(int $membershipId, string $role): void
    {
        $this->authorizeAdmin();
        $membership = $this->membership($membershipId);
        $this->guardOwnerAndSelf($membership);

        if (! in_array($role, [Role::Admin->value, Role::Member->value], true)) {
            return;
        }

        $membership->update(['role' => $role]);
        $this->notify('Role updated');
    }

    public function setRate(int $membershipId, string $rate): void
    {
        $this->authorizeAdmin();
        if ($rate !== '' && (! is_numeric($rate) || (float) $rate < 0)) {
            $this->notify('Enter a valid rate.', 'error');

            return;
        }

        $this->membership($membershipId)->update(['hourly_rate' => Money::toCents($rate)]);
        $this->notify('Rate updated — applies to new and edited entries');
    }

    public function setStatus(int $membershipId, string $status): void
    {
        $this->authorizeAdmin();
        $membership = $this->membership($membershipId);
        $this->guardOwnerAndSelf($membership);

        if ($membership->status === MemberStatus::Invited) {
            return;
        }

        $membership->update(['status' => $status === 'inactive' ? MemberStatus::Inactive : MemberStatus::Active]);
        $this->notify($status === 'inactive' ? 'Member deactivated' : 'Member activated');
    }

    public function remove(int $membershipId): void
    {
        $this->authorizeAdmin();
        $membership = $this->membership($membershipId);
        $this->guardOwnerAndSelf($membership);

        DB::transaction(function () use ($membership): void {
            DB::table('user_group_user')->where('user_id', $membership->user_id)
                ->whereIn('user_group_id', UserGroup::where('workspace_id', $this->workspace->id)->select('id'))->delete();
            $membership->delete();
        });
        $this->notify('Member removed (their time entries are kept)');
    }

    private function membership(int $id): Membership
    {
        return Membership::query()->where('workspace_id', $this->workspace->id)->findOrFail($id);
    }

    private function guardOwnerAndSelf(Membership $membership): void
    {
        abort_if($membership->role === Role::Owner, 403, 'The workspace owner cannot be changed.');
        abort_if($membership->user_id === $this->user()->id, 403, 'You cannot change your own membership.');
    }

    /* ------------------------------------------------------------- Groups */

    public function createGroup(): void
    {
        $this->authorizeAdmin();
        $this->validate(['newGroup' => ['required', 'string', 'max:100', Rule::unique('user_groups', 'name')->where('workspace_id', $this->workspace->id)]], [], ['newGroup' => 'group name']);
        UserGroup::create(['workspace_id' => $this->workspace->id, 'name' => trim($this->newGroup)]);
        $this->reset('newGroup');
        $this->notify('Group created');
    }

    public function editGroup(int $id): void
    {
        $this->authorizeAdmin();
        $group = $this->group($id)->load('users:id');
        $this->resetValidation();
        $this->editingGroupId = $group->id;
        $this->groupName = $group->name;
        $this->groupMembers = $group->users->pluck('id')->all();
        $this->dispatch('open-modal', name: 'group-form');
    }

    public function saveGroup(): void
    {
        $this->authorizeAdmin();
        $group = $this->group((int) $this->editingGroupId);
        $this->validate(['groupName' => ['required', 'string', 'max:100', Rule::unique('user_groups', 'name')->where('workspace_id', $this->workspace->id)->ignore($group->id)]], [], ['groupName' => 'group name']);

        $validIds = $this->workspace->users()->whereIn('users.id', array_map('intval', $this->groupMembers))->pluck('users.id');
        $group->update(['name' => trim($this->groupName)]);
        $group->users()->sync($validIds);

        $this->dispatch('close-modal', name: 'group-form');
        $this->notify('Group saved');
    }

    public function deleteGroup(int $id): void
    {
        $this->authorizeAdmin();
        $this->group($id)->delete();
        $this->notify('Group deleted');
    }

    private function group(int $id): UserGroup
    {
        return UserGroup::query()->where('workspace_id', $this->workspace->id)->findOrFail($id);
    }

    public function render(): mixed
    {
        $search = trim($this->search);
        $groupsByUser = DB::table('user_group_user')
            ->join('user_groups', 'user_groups.id', '=', 'user_group_user.user_group_id')
            ->where('user_groups.workspace_id', $this->workspace->id)
            ->get(['user_group_user.user_id', 'user_groups.name'])
            ->groupBy('user_id');

        return view('livewire.team', [
            'members' => Membership::query()
                ->where('workspace_id', $this->workspace->id)
                ->with('user')
                ->when($this->status !== 'all', fn ($q) => $q->whereIn('status', $this->status === 'active' ? ['active', 'invited'] : ['inactive']))
                ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->whereLike('name', '%'.addcslashes($search, '%_\\').'%')->orWhereLike('email', '%'.addcslashes($search, '%_\\').'%')))
                ->get()
                ->sortBy(fn (Membership $m): string => mb_strtolower($m->user->name))
                ->values(),
            'groupsByUser' => $groupsByUser,
            'groups' => UserGroup::query()->where('workspace_id', $this->workspace->id)->with('users:id,name,avatar_color')->orderBy('name')->get(),
            'allMembers' => $this->workspace->users()->orderBy('name')->get(['users.id', 'users.name']),
            'roles' => [Role::Admin, Role::Member],
        ]);
    }
}
