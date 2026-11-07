<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;

trait InteractsWithWorkspace
{
    #[Computed]
    public function workspace(): Workspace
    {
        return $this->user()->currentWorkspace;
    }

    public function user(): User
    {
        return auth()->user();
    }

    #[Computed]
    public function isAdmin(): bool
    {
        return $this->user()->isAdminOf($this->workspace);
    }

    protected function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin, 403, 'Only workspace admins can do this.');
    }

    protected function notify(string $message, string $type = 'success'): void
    {
        $this->dispatch('notify', message: $message, type: $type);
    }

    protected function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->user()->timezone);
    }

    public function timeFormat(): string
    {
        return $this->user()->time_format === '12' ? 'g:i A' : 'H:i';
    }
}
