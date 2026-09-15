<?php

namespace App\Http\Controllers;

use App\Actions\MediaIngestionService;
use App\Models\Folder;
use App\Models\MediaFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MediaUploadController extends Controller
{
    public function create(Request $request): View
    {
        $folders = Folder::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('media.upload', [
            'folders' => $folders,
            'uploadAttemptId' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, MediaIngestionService $ingestion): RedirectResponse|JsonResponse
    {
        $attemptId = $request->input('upload_attempt_id');

        if ($attemptId !== null && (! is_string($attemptId) || ! Str::isUuid($attemptId))) {
            throw ValidationException::withMessages([
                'upload_attempt_id' => 'The upload attempt identifier is invalid.',
            ]);
        }

        $attemptId ??= (string) Str::uuid();

        if (MediaFile::query()
            ->where('upload_attempt_id', $attemptId)
            ->where('user_id', '!=', $request->user()->id)
            ->exists()) {
            abort(403, 'This upload attempt belongs to another user.');
        }

        $existing = $ingestion->findByAttempt($request->user(), $attemptId);

        if ($existing !== null) {
            return $this->successResponse($request, $existing);
        }

        if ($this->uploadedFileCount($request->allFiles()) !== 1) {
            throw ValidationException::withMessages([
                'media_file' => 'Submit exactly one media file per upload attempt.',
            ]);
        }

        $validated = $request->validate([
            'media_file' => ['required', 'file'],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('folders', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $file = $validated['media_file'];

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'media_file' => 'Submit exactly one media file per upload attempt.',
            ]);
        }

        try {
            $mediaFile = $ingestion->ingest(
                $request->user(),
                $file,
                $attemptId,
                isset($validated['folder_id']) ? (int) $validated['folder_id'] : null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The upload could not be completed. Please retry using the same upload attempt.',
                ], 500);
            }

            return back()
                ->withInput()
                ->withErrors(['media_file' => 'The upload could not be completed. Please try again.']);
        }

        return $this->successResponse($request, $mediaFile);
    }

    private function successResponse(Request $request, MediaFile $mediaFile): RedirectResponse|JsonResponse
    {
        $destination = route('media.show', $mediaFile);

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => $destination,
                'media_file_id' => $mediaFile->id,
            ]);
        }

        return redirect()->to($destination);
    }

    /** @param array<string, mixed> $files */
    private function uploadedFileCount(array $files): int
    {
        $count = 0;

        foreach ($files as $file) {
            $count += is_array($file)
                ? $this->uploadedFileCount($file)
                : ($file instanceof UploadedFile ? 1 : 0);
        }

        return $count;
    }
}
