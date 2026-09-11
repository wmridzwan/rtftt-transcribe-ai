<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FolderController extends Controller
{
    public function show(Folder $folder): View
    {
        $this->authorize('view', $folder);

        $folder->load(['mediaFiles' => function ($query) {
            $query->latest();
        }]);

        return view('folders.show', compact('folder'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Folder::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $request->user()->folders()->create($validated);

        return redirect()->back()->with('success', 'Folder created successfully.');
    }

    public function update(Request $request, Folder $folder): RedirectResponse
    {
        $this->authorize('update', $folder);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $folder->update($validated);

        return redirect()->back()->with('success', 'Folder renamed successfully.');
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);

        $folder->mediaFiles()->update(['folder_id' => null]);
        $folder->delete();

        return redirect()->route('folders.index')
            ->with('success', 'Folder deleted successfully.');
    }
}
