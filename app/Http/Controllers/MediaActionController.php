<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaActionController extends Controller
{
    public function rename(Request $request, MediaFile $mediaFile): RedirectResponse
    {
        $this->authorize('update', $mediaFile);

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
        ]);

        $mediaFile->update(['display_name' => $validated['display_name']]);

        return redirect()->back()->with('success', 'Media file renamed successfully.');
    }

    public function destroy(MediaFile $mediaFile): RedirectResponse
    {
        $this->authorize('delete', $mediaFile);

        if ($mediaFile->transcriptions()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete media file with associated transcriptions.');
        }

        if ($mediaFile->storage_path && Storage::exists($mediaFile->storage_path)) {
            Storage::delete($mediaFile->storage_path);
        }

        $mediaFile->delete();

        return redirect()->route('media.index')
            ->with('success', 'Media file deleted successfully.');
    }

    public function download(MediaFile $mediaFile)
    {
        $this->authorize('view', $mediaFile);

        if (! $mediaFile->storage_path || ! Storage::exists($mediaFile->storage_path)) {
            abort(404, 'Physical file not found.');
        }

        return Storage::download($mediaFile->storage_path, $mediaFile->display_name ?? $mediaFile->original_filename);
    }

    public function moveToFolder(Request $request, MediaFile $mediaFile): RedirectResponse
    {
        $this->authorize('update', $mediaFile);

        $validated = $request->validate([
            'folder_id' => 'nullable|integer|exists:folders,id',
        ]);

        $mediaFile->update(['folder_id' => $validated['folder_id']]);

        return redirect()->back()->with('success', 'Media file moved successfully.');
    }
}
