<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Project;
use App\Support\Timezone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
class Profile extends Component
{
    use InteractsWithWorkspace;

    public string $name = '';

    public string $email = '';

    public string $timezone = 'UTC';

    public int $weekStart = 1;

    public string $timeFormat = '24';

    public string $avatarColor = '#03a9f4';

    public string $currentPassword = '';

    public string $newPassword = '';

    public function mount(): void
    {
        $u = $this->user();
        $this->fill([
            'name' => $u->name,
            'email' => $u->email,
            'timezone' => Timezone::normalize($u->timezone),
            'weekStart' => $u->week_start,
            'timeFormat' => $u->time_format,
            'avatarColor' => $u->avatar_color,
        ]);
    }

    public function save(): void
    {
        $u = $this->user();
        $data = $this->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($u->id)],
            'timezone' => 'required|timezone:all_with_bc',
            'weekStart' => 'required|integer|in:0,1,6',
            'timeFormat' => 'required|in:12,24',
            'avatarColor' => ['required', Rule::in(Project::COLORS)],
        ]);

        $u->update([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'timezone' => $data['timezone'],
            'week_start' => $data['weekStart'],
            'time_format' => $data['timeFormat'],
            'avatar_color' => $data['avatarColor'],
        ]);

        $this->notify('Profile saved');
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'newPassword' => ['required', 'string', 'min:8', 'different:currentPassword'],
        ]);

        $this->user()->update(['password' => Hash::make($this->newPassword)]);
        $this->reset('currentPassword', 'newPassword');
        $this->notify('Password changed');
    }

    public function render(): mixed
    {
        return view('livewire.profile', [
            'timezones' => Timezone::options($this->timezone),
            'colors' => Project::COLORS,
        ]);
    }
}
