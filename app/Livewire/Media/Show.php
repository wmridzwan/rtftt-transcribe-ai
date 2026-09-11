<?php

namespace App\Livewire\Media;

use App\Models\Folder;
use App\Models\MediaFile;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Media File')]
class Show extends Component
{
    public ?MediaFile $mediaFile = null;

    public array $folders = [];

    public bool $showRenameModal = false;

    public bool $showMoveModal = false;

    public bool $showDeleteModal = false;

    public bool $confirmCascade = false;

    public string $renameName = '';

    public ?int $moveFolderId = null;

    public function render()
    {
        return view('livewire.media.show');
    }

    public function mount(MediaFile $mediaFile): void
    {
        $this->authorize('view', $mediaFile);

        $this->mediaFile = $mediaFile->load(['transcriptions', 'folder']);
        $this->folders = Folder::where('user_id', $mediaFile->user_id)
            ->withCount('mediaFiles')
            ->latest()
            ->get()
            ->toArray();

        $this->renameName = $this->mediaFile->display_name;
    }

    public function openRenameModal(): void
    {
        $this->renameName = $this->mediaFile->display_name;
        $this->showRenameModal = true;
    }

    public function rename(): void
    {
        $validated = $this->validate([
            'renameName' => 'required|string|max:255',
        ]);

        $this->authorize('update', $this->mediaFile);

        $this->mediaFile->update(['display_name' => $this->renameName]);
        $this->mediaFile->refresh();

        $this->showRenameModal = false;

        Flux::toast(variant: 'success', text: 'Media file renamed successfully.');
    }

    public function openMoveModal(): void
    {
        $this->moveFolderId = $this->mediaFile->folder_id;
        $this->showMoveModal = true;
    }

    public function moveToFolder(): void
    {
        $this->authorize('update', $this->mediaFile);

        $this->validate([
            'moveFolderId' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('user_id', $this->mediaFile->user_id)],
        ]);

        $this->mediaFile->update(['folder_id' => $this->moveFolderId]);
        $this->mediaFile->load('folder');
        $this->mediaFile->refresh();

        $this->showMoveModal = false;

        Flux::toast(variant: 'success', text: 'Media file moved successfully.');
    }

    public function openDeleteModal(): void
    {
        $this->confirmCascade = false;
        $this->showDeleteModal = true;
    }

    public function destroy(): void
    {
        $this->authorize('delete', $this->mediaFile);

        $hasTranscriptions = $this->mediaFile->transcriptions()->exists();

        $this->validate([
            'confirmCascade' => $hasTranscriptions
                ? ['accepted']
                : ['nullable', 'boolean'],
        ]);

        if ($this->mediaFile->storage_path && \Storage::exists($this->mediaFile->storage_path)) {
            \Storage::delete($this->mediaFile->storage_path);
        }

        $this->mediaFile->delete();

        $this->reset('confirmCascade', 'showDeleteModal');

        $this->redirect(route('media.index'));
    }
}
