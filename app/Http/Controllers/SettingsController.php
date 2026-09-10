<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $storageUsed = $user->mediaFiles()->sum('file_size_bytes');
        $totalFiles = $user->mediaFiles()->count();
        $totalTranscriptions = $user->transcriptions()->count();

        return view('settings.index', compact('storageUsed', 'totalFiles', 'totalTranscriptions'));
    }
}
