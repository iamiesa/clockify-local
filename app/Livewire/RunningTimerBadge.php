<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Compact running-timer indicator shown in the top bar on every page.
 */
class RunningTimerBadge extends Component
{
    use InteractsWithWorkspace;

    #[Locked]
    public bool $onTrackerPage = false;

    public function mount(): void
    {
        $this->onTrackerPage = request()->routeIs('tracker');
    }

    #[On('timer-changed')]
    public function refreshBadge(): void {}

    public function render(): mixed
    {
        return view('livewire.running-timer-badge', [
            'running' => $this->user()->runningEntry($this->workspace)?->load('project'),
        ]);
    }
}
