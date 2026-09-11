<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $query = MediaFile::query();

        if (! $isAdmin) {
            $query->where('user_id', $user->id);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('media_type', $type);
        }

        if ($folderId = $request->input('folder')) {
            if ($folderId === 'none') {
                $query->whereNull('folder_id');
            } else {
                $query->where('folder_id', $folderId);
            }
        }

        $mediaFiles = $query->with('folder')->latest()->paginate(15)->withQueryString();

        $folders = Folder::where('user_id', $user->id)->withCount('mediaFiles')->latest()->get();

        return view('media.index', compact('mediaFiles', 'folders'));
    }
}
