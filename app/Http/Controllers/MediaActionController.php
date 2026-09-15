<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function destroy(Request $request, MediaFile $mediaFile): RedirectResponse
    {
        $this->authorize('delete', $mediaFile);

        $hasTranscriptions = $mediaFile->transcriptions()->exists();

        $request->validate([
            'confirm_cascade' => $hasTranscriptions
                ? ['accepted']
                : ['nullable', 'boolean'],
        ]);

        $storage = MediaFile::storage();

        if ($mediaFile->storage_path && $storage->exists($mediaFile->storage_path)) {
            $storage->delete($mediaFile->storage_path);
        }

        $mediaFile->delete();

        return redirect()->route('media.index')
            ->with('success', 'Media file deleted successfully.');
    }

    public function download(MediaFile $mediaFile): StreamedResponse
    {
        $this->authorize('view', $mediaFile);
        $storage = MediaFile::storage();

        if (! $mediaFile->storage_path || ! $storage->exists($mediaFile->storage_path)) {
            abort(404, 'Physical file not found.');
        }

        return $storage->download($mediaFile->storage_path, $mediaFile->display_name ?? $mediaFile->original_filename);
    }

    public function moveToFolder(Request $request, MediaFile $mediaFile): RedirectResponse
    {
        $this->authorize('update', $mediaFile);

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('user_id', $mediaFile->user_id)],
        ]);

        $mediaFile->update(['folder_id' => $validated['folder_id']]);

        return redirect()->back()->with('success', 'Media file moved successfully.');
    }
}
