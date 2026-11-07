<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Client;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Clients')]
class Clients extends Component
{
    use InteractsWithWorkspace;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $search = '';

    public string $newName = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $address = '';

    public string $note = '';

    public function create(): void
    {
        $this->authorizeAdmin();
        $this->validate([
            'newName' => ['required', 'string', 'max:150', Rule::unique('clients', 'name')->where('workspace_id', $this->workspace->id)],
        ], [], ['newName' => 'client name']);

        Client::create(['workspace_id' => $this->workspace->id, 'name' => trim($this->newName)]);
        $this->reset('newName');
        $this->notify('Client created');
    }

    public function edit(int $id): void
    {
        $this->authorizeAdmin();
        $client = $this->find($id);
        $this->resetValidation();
        $this->editingId = $client->id;
        $this->fill($client->only(['name']) + ['email' => (string) $client->email, 'address' => (string) $client->address, 'note' => (string) $client->note]);
        $this->dispatch('open-modal', name: 'client-form');
    }

    public function update(): void
    {
        $this->authorizeAdmin();
        $client = $this->find((int) $this->editingId);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('clients', 'name')->where('workspace_id', $this->workspace->id)->ignore($client->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $client->update(array_map(fn ($v) => $v === '' ? null : $v, $data) + ['name' => trim($data['name'])]);
        $this->dispatch('close-modal', name: 'client-form');
        $this->notify('Client updated');
    }

    public function archive(int $id, bool $archived = true): void
    {
        $this->authorizeAdmin();
        $this->find($id)->update(['archived' => $archived]);
        $this->notify($archived ? 'Client archived' : 'Client restored');
    }

    public function delete(int $id): void
    {
        $this->authorizeAdmin();
        $this->find($id)->delete();
        $this->notify('Client deleted');
    }

    private function find(int $id): Client
    {
        return Client::query()->where('workspace_id', $this->workspace->id)->findOrFail($id);
    }

    public function render(): mixed
    {
        $search = trim($this->search);

        return view('livewire.clients', [
            'clients' => Client::query()
                ->where('workspace_id', $this->workspace->id)
                ->withCount('projects')
                ->when($this->status !== 'all', fn ($q) => $q->where('archived', $this->status === 'archived'))
                ->when($search !== '', fn ($q) => $q->whereLike('name', '%'.addcslashes($search, '%_\\').'%'))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
