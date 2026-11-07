<?php

namespace App\Livewire\Auth;

use App\Actions\InvitationAcceptAction;
use App\Enums\MemberStatus;
use App\Models\Membership;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Join workspace')]
class AcceptInvitation extends Component
{
    #[Locked]
    public string $token;

    public string $name = '';

    public string $password = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->name = $this->membership()->user->name;
    }

    public function membership(): Membership
    {
        return Membership::query()
            ->with(['user', 'workspace'])
            ->where('invite_token', $this->token)
            ->where('status', MemberStatus::Invited)
            ->firstOrFail();
    }

    public function accept(InvitationAcceptAction $accept): mixed
    {
        $membership = $this->membership();
        $user = $membership->user;

        if ($user->password === null) {
            $data = $this->validate(['name' => 'required|string|max:100', 'password' => 'required|string|min:8']);
            $user->update($data);
        } elseif (auth()->id() !== $user->id) {
            return $this->redirect(route('login'));
        }

        $accept($membership, $user);
        Auth::login($user, true);

        return $this->redirect(route('tracker'));
    }

    public function render(): mixed
    {
        return view('livewire.auth.accept-invitation', ['membership' => $this->membership()]);
    }
}
