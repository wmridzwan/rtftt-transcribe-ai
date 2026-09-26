<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        // P7-011: purged sources report their retention state (410 Gone),
        // never a bare 404. History/exports remain available from
        // retained rows; only the bytes are gone.
        if ($mediaFile->purged_at !== null) {
            abort(Response::HTTP_GONE, 'Source media was purged under the 30-day retention policy. Transcript history and text exports remain available.');
        }

        $storage = MediaFile::storage();

        if (! $mediaFile->storage_path || ! $storage->exists($mediaFile->storage_path)) {
            abort(404, 'Physical file not found.');
        }

        return $storage->download($mediaFile->storage_path, $mediaFile->display_name ?? $mediaFile->original_filename);
    }

    /**
     * Authorized private-media byte-range streaming (P4-002, ADR-019).
     *
     * Serves the owning user or an admin. Supports a single HTTP byte range.
     * Files are streamed in bounded chunks and are never loaded wholly into
     * PHP memory. No filesystem/storage path is exposed.
     */
    public function stream(Request $request, MediaFile $mediaFile): StreamedResponse|Response
    {
        $this->authorize('view', $mediaFile);

        // P7-011: see download() — purged sources are 410 Gone.
        if ($mediaFile->purged_at !== null) {
            abort(Response::HTTP_GONE, 'Source media was purged under the 30-day retention policy. Transcript history and text exports remain available.');
        }

        $storage = MediaFile::storage();

        if (! $mediaFile->storage_path || ! $storage->exists($mediaFile->storage_path)) {
            abort(404, 'Physical file not found.');
        }

        $size = (int) $storage->size($mediaFile->storage_path);

        // P7-004 / TD-011 zero-byte disposition (explicit): an empty object
        // has no satisfiable range. Without a Range header it is served as
        // an empty 200 (Content-Length 0, never 1); any Range header on an
        // empty object is 416 with the RFC-mandated `bytes */0` marker.
        // Empty uploads are accepted by ingestion (size 0 passes the byte
        // ceiling and carries a detected MIME type), so this edge is
        // reachable and must not lie about its length.
        if ($size === 0) {
            if ($request->header('Range') !== null && trim((string) $request->header('Range')) !== '') {
                return response('', Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, [
                    'Content-Range' => 'bytes */0',
                    'Accept-Ranges' => 'bytes',
                ]);
            }

            return response('', Response::HTTP_OK, [
                'Content-Type' => $mediaFile->mime_type,
                'Accept-Ranges' => 'bytes',
                'Content-Length' => '0',
            ]);
        }

        $range = $this->resolveRange($request->header('Range'), $size);

        if ($range['status'] === Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE) {
            return response('', Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, [
                'Content-Range' => 'bytes */'.$size,
                'Accept-Ranges' => 'bytes',
            ]);
        }

        $isPartial = $range['status'] === Response::HTTP_PARTIAL_CONTENT;
        $start = $range['start'] ?? 0;
        $end = $range['end'] ?? max(0, $size - 1);
        $length = max(0, $end - $start + 1);

        $headers = [
            'Content-Type' => $mediaFile->mime_type,
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $length,
        ];

        if ($isPartial) {
            $headers['Content-Range'] = sprintf('bytes %d-%d/%d', $start, $end, $size);
        }

        return response()->stream(function () use ($storage, $mediaFile, $start, $length): void {
            $stream = $storage->readStream((string) $mediaFile->storage_path);

            if ($stream === null) {
                return;
            }

            if ($start > 0) {
                fseek($stream, $start);
            }

            $remaining = $length;
            $chunkSize = 8192;

            while ($remaining > 0 && ! feof($stream)) {
                $buffer = fread($stream, (int) min($chunkSize, $remaining));

                if ($buffer === false || $buffer === '') {
                    break;
                }

                echo $buffer;
                $remaining -= strlen($buffer);
                flush();
            }

            fclose($stream);
        }, $isPartial ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK, $headers);
    }

    /**
     * Resolve a single HTTP Range header against a known file size.
     *
     * Unsupported/multi/malformed ranges are ignored (200 full response).
     * Unsatisfiable ranges return 416.
     *
     * @return array{status: int, start: int|null, end: int|null}
     */
    private function resolveRange(?string $header, int $size): array
    {
        $full = ['status' => Response::HTTP_OK, 'start' => null, 'end' => null];

        if ($header === null || $header === '' || ! str_starts_with($header, 'bytes=')) {
            return $full;
        }

        $spec = trim(substr($header, 6));

        if (str_contains($spec, ',')) {
            return $full;
        }

        if (preg_match('/^(\d*)-(\d*)$/', $spec, $matches) !== 1) {
            return $full;
        }

        $startRaw = $matches[1];
        $endRaw = $matches[2];

        if ($startRaw === '' && $endRaw === '') {
            return $full;
        }

        if ($startRaw === '') {
            $suffix = (int) $endRaw;

            if ($suffix === 0) {
                return ['status' => Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, 'start' => null, 'end' => null];
            }

            $start = max(0, $size - min($suffix, $size));

            return ['status' => Response::HTTP_PARTIAL_CONTENT, 'start' => $start, 'end' => max(0, $size - 1)];
        }

        $start = (int) $startRaw;

        if ($start >= $size) {
            return ['status' => Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, 'start' => null, 'end' => null];
        }

        if ($endRaw === '') {
            return ['status' => Response::HTTP_PARTIAL_CONTENT, 'start' => $start, 'end' => max(0, $size - 1)];
        }

        $end = (int) $endRaw;

        if ($end < $start) {
            return $full;
        }

        return ['status' => Response::HTTP_PARTIAL_CONTENT, 'start' => $start, 'end' => min($end, max(0, $size - 1))];
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
