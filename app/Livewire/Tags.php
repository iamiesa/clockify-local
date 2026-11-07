<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Tag;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Tags')]
class Tags extends Component
{
    use InteractsWithWorkspace;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $search = '';

    public string $newName = '';

    public function create(): void
    {
        abort_unless($this->canCreate(), 403);
        $this->validate([
            'newName' => ['required', 'string', 'max:100', Rule::unique('tags', 'name')->where('workspace_id', $this->workspace->id)],
        ], [], ['newName' => 'tag name']);

        Tag::create(['workspace_id' => $this->workspace->id, 'name' => trim($this->newName)]);
        $this->reset('newName');
        $this->notify('Tag created');
    }

    public function rename(int $id, string $name): void
    {
        $this->authorizeAdmin();
        $name = trim($name);
        $tag = $this->find($id);

        if ($name === '' || mb_strlen($name) > 100 || Tag::where('workspace_id', $this->workspace->id)->where('name', $name)->whereKeyNot($id)->exists()) {
            $this->notify('Tag names must be unique and not empty.', 'error');
            $tag->touch();

            return;
        }

        $tag->update(['name' => $name]);
        $this->notify('Tag renamed');
    }

    public function archive(int $id, bool $archived = true): void
    {
        $this->authorizeAdmin();
        $this->find($id)->update(['archived' => $archived]);
        $this->notify($archived ? 'Tag archived' : 'Tag restored');
    }

    public function delete(int $id): void
    {
        $this->authorizeAdmin();
        $this->find($id)->delete();
        $this->notify('Tag deleted');
    }

    private function canCreate(): bool
    {
        return $this->isAdmin || $this->workspace->members_can_create_tags;
    }

    private function find(int $id): Tag
    {
        return Tag::query()->where('workspace_id', $this->workspace->id)->findOrFail($id);
    }

    public function render(): mixed
    {
        $search = trim($this->search);

        return view('livewire.tags', [
            'canCreate' => $this->canCreate(),
            'tags' => Tag::query()
                ->where('workspace_id', $this->workspace->id)
                ->withCount('timeEntries')
                ->when($this->status !== 'all', fn ($q) => $q->where('archived', $this->status === 'archived'))
                ->when($search !== '', fn ($q) => $q->whereLike('name', '%'.addcslashes($search, '%_\\').'%'))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
