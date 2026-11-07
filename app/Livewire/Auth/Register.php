<?php

namespace App\Livewire\Auth;

use App\Actions\WorkspaceCreateAction;
use App\Models\Project;
use App\Models\User;
use App\Support\Timezone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Sign up')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $timezone = 'UTC';

    public function register(WorkspaceCreateAction $createWorkspace): mixed
    {
        // Timezone is detected silently by the browser, so never block sign-up on it.
        $this->timezone = Timezone::normalize($this->timezone);

        $data = $this->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'timezone' => 'required|timezone:all_with_bc',
        ]);

        $user = DB::transaction(function () use ($data, $createWorkspace): User {
            $user = User::create([
                ...$data,
                'email' => mb_strtolower($data['email']),
                'avatar_color' => Project::COLORS[array_rand(Project::COLORS)],
            ]);
            $createWorkspace($user, "{$user->name}'s workspace");

            return $user;
        });

        Auth::login($user, true);
        session()->regenerate();

        return $this->redirect(route('tracker'));
    }

    public function render(): mixed
    {
        return view('livewire.auth.register');
    }
}
