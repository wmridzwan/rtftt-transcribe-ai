<?php

namespace App\Livewire\Folders;

use App\Models\Folder;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Folders')]
class Index extends Component
{
    public bool $showCreateModal = false;

    public bool $showRenameModal = false;

    public bool $showDeleteModal = false;

    public string $newFolderName = '';

    public int $renameFolderId = 0;

    public string $renameFolderName = '';

    public int $deleteFolderId = 0;

    public string $deleteFolderName = '';

    public int $deleteMediaCount = 0;

    public function render()
    {
        $folders = Folder::where('user_id', auth()->id())
            ->withCount('mediaFiles')
            ->latest()
            ->get();

        return view('livewire.folders.index', compact('folders'));
    }

    public function store(): void
    {
        $validated = $this->validate([
            'newFolderName' => 'required|string|max:255',
        ]);

        auth()->user()->folders()->create(['name' => $this->newFolderName]);

        $this->reset('newFolderName', 'showCreateModal');

        Flux::toast(variant: 'success', text: 'Folder created successfully.');
    }

    public function openRenameModal(int $folderId, string $folderName): void
    {
        $this->renameFolderId = $folderId;
        $this->renameFolderName = $folderName;
        $this->showRenameModal = true;
    }

    public function update(): void
    {
        $validated = $this->validate([
            'renameFolderName' => 'required|string|max:255',
        ]);

        $folder = Folder::findOrFail($this->renameFolderId);
        $this->authorize('update', $folder);

        $folder->update(['name' => $this->renameFolderName]);

        $this->reset('renameFolderId', 'renameFolderName', 'showRenameModal');

        Flux::toast(variant: 'success', text: 'Folder renamed successfully.');
    }

    public function openDeleteModal(int $folderId, string $folderName, int $mediaCount): void
    {
        $this->deleteFolderId = $folderId;
        $this->deleteFolderName = $folderName;
        $this->deleteMediaCount = $mediaCount;
        $this->showDeleteModal = true;
    }

    public function destroy(): void
    {
        $folder = Folder::findOrFail($this->deleteFolderId);
        $this->authorize('delete', $folder);

        $folder->mediaFiles()->update(['folder_id' => null]);
        $folder->delete();

        $this->reset('deleteFolderId', 'deleteFolderName', 'deleteMediaCount', 'showDeleteModal');

        Flux::toast(variant: 'success', text: 'Folder deleted successfully.');
    }
}
