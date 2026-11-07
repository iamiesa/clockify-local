<?php

namespace App\Actions;

use App\Enums\MemberStatus;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * One-time (idempotent) move to shared mode: merges every other workspace into the shared one,
 * keeping all projects, tasks, clients, tags, groups and time entries. Same-named clients, tags and
 * groups are merged; a same-named project is kept and suffixed with its previous workspace name.
 *
 * @return array{workspace: ?int, merged: int, users: int}
 */
class WorkspaceConsolidateAction
{
    public function __construct(private WorkspaceOwnerAssignAction $assignOwner) {}

    public function __invoke(): array
    {
        $shared = WorkspaceJoinAction::sharedWorkspace();
        if (! $shared) {
            return ['workspace' => null, 'merged' => 0, 'users' => 0];
        }

        $merged = 0;

        DB::transaction(function () use ($shared, &$merged): void {
            foreach (Workspace::query()->whereKeyNot($shared->id)->orderBy('id')->get() as $other) {
                $this->merge($other, $shared);
                $merged++;
            }

            foreach (User::query()->get() as $user) {
                $membership = Membership::query()->firstOrNew(['workspace_id' => $shared->id, 'user_id' => $user->id]);
                if (! $membership->exists) {
                    $membership->fill(['role' => Role::Member, 'status' => $user->password === null ? MemberStatus::Invited : MemberStatus::Active])->save();
                }
                $user->forceFill(['current_workspace_id' => $shared->id])->save();
            }

            DB::table('projects')->where('workspace_id', $shared->id)->update(['is_public' => true]);
            $shared->forceFill(['members_can_create_projects' => true, 'members_can_create_tags' => true])->save();

            $ownerEmail = config('tracker.owner_email');
            $owner = $ownerEmail ? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($ownerEmail))])->first() : null;
            if ($owner) {
                ($this->assignOwner)($shared, $owner);
            }
        });

        return ['workspace' => $shared->id, 'merged' => $merged, 'users' => User::query()->count()];
    }

    private function merge(Workspace $from, Workspace $into): void
    {
        $clientMap = $this->mergeByName('clients', $from, $into);
        $tagMap = $this->mergeByName('tags', $from, $into);
        $groupMap = $this->mergeByName('user_groups', $from, $into);

        // Repoint pivots of merged (duplicate) tags and groups, then drop the duplicates.
        foreach ($tagMap as $old => $new) {
            foreach (DB::table('tag_time_entry')->where('tag_id', $old)->pluck('time_entry_id') as $entryId) {
                DB::table('tag_time_entry')->insertOrIgnore(['tag_id' => $new, 'time_entry_id' => $entryId]);
            }
            DB::table('tags')->where('id', $old)->delete();
        }
        foreach ($groupMap as $old => $new) {
            foreach (DB::table('user_group_user')->where('user_group_id', $old)->pluck('user_id') as $userId) {
                DB::table('user_group_user')->insertOrIgnore(['user_group_id' => $new, 'user_id' => $userId]);
            }
            DB::table('user_groups')->where('id', $old)->delete();
        }

        foreach (DB::table('projects')->where('workspace_id', $from->id)->orderBy('id')->get() as $project) {
            $clientId = $project->client_id !== null ? ($clientMap[$project->client_id] ?? $project->client_id) : null;
            $name = $project->name;
            $taken = fn (string $candidate): bool => DB::table('projects')->where('workspace_id', $into->id)
                ->where('name', $candidate)->when($clientId === null, fn ($q) => $q->whereNull('client_id'), fn ($q) => $q->where('client_id', $clientId))->exists();
            for ($i = 1; $taken($name); $i++) {
                $name = mb_substr($project->name, 0, 120).' ('.mb_substr($from->name, 0, 20).($i > 1 ? " {$i}" : '').')';
            }
            DB::table('projects')->where('id', $project->id)->update(['workspace_id' => $into->id, 'client_id' => $clientId, 'name' => $name, 'is_public' => true]);
        }

        DB::table('time_entries')->where('workspace_id', $from->id)->update(['workspace_id' => $into->id]);

        foreach (Membership::query()->where('workspace_id', $from->id)->get() as $membership) {
            $exists = Membership::query()->where('workspace_id', $into->id)->where('user_id', $membership->user_id)->exists();
            if (! $exists) {
                Membership::query()->create([
                    'workspace_id' => $into->id,
                    'user_id' => $membership->user_id,
                    'role' => $membership->role === Role::Owner ? Role::Member : $membership->role,
                    'status' => $membership->status,
                    'hourly_rate' => $membership->hourly_rate,
                    'invite_token' => $membership->invite_token,
                ]);
            }
        }

        $from->delete();
    }

    /**
     * Moves name-unique rows (clients, tags, groups) into the target workspace.
     *
     * @return array<int, int> ids of duplicates in $from => existing id in $into
     */
    private function mergeByName(string $table, Workspace $from, Workspace $into): array
    {
        $existing = DB::table($table)->where('workspace_id', $into->id)->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $duplicates = [];

        foreach (DB::table($table)->where('workspace_id', $from->id)->get(['id', 'name']) as $row) {
            if (isset($existing[mb_strtolower($row->name)])) {
                $duplicates[$row->id] = $existing[mb_strtolower($row->name)];
                if ($table === 'clients') {
                    DB::table('projects')->where('client_id', $row->id)->update(['client_id' => $existing[mb_strtolower($row->name)]]);
                    DB::table('clients')->where('id', $row->id)->delete();
                }
            } else {
                DB::table($table)->where('id', $row->id)->update(['workspace_id' => $into->id]);
                $existing[mb_strtolower($row->name)] = $row->id;
            }
        }

        return $table === 'clients' ? [] : $duplicates;
    }
}
