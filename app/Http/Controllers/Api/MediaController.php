<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Entry;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo and video handling.
 *
 * The bytes never pass through PHP. The app asks for a signed URL, uploads
 * straight to R2, then tells us it succeeded. That keeps upload_max_filesize,
 * request timeouts and VPS bandwidth out of the picture entirely — and R2
 * charges no egress on the way back down.
 */
class MediaController extends Controller
{
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/heic' => 'heic',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
    ];

    /**
     * Step 1 — hand the app a URL it can PUT the file to.
     *
     * Nothing is written to the database yet: an abandoned upload should leave
     * no trace beyond an orphaned object, which the bucket lifecycle rule sweeps.
     */
    public function presign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mime' => ['required', 'string', 'in:'.implode(',', array_keys(self::ALLOWED_MIMES))],
        ]);

        $extension = self::ALLOWED_MIMES[$validated['mime']];
        $key = sprintf('media/%d/%s.%s', $request->user()->id, Str::uuid(), $extension);

        $url = Storage::disk('r2')->temporaryUploadUrl(
            $key,
            now()->addSeconds((int) config('filesystems.disks.r2.upload_ttl'))
        );

        return response()->json([
            'key' => $key,
            'url' => $url['url'],
            // R2 requires the same Content-Type on the PUT that was signed.
            'headers' => $url['headers'],
            'expiresIn' => (int) config('filesystems.disks.r2.upload_ttl'),
        ]);
    }

    /**
     * Step 2 — the upload finished; record it against an entry.
     *
     * We verify the object actually exists so a failed PUT cannot leave a row
     * pointing at nothing.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['sometimes', 'uuid'],
            'entryId' => ['required', 'uuid'],
            'key' => ['required', 'string', 'max:1024'],
            'kind' => ['required', 'in:photo,video'],
            'mime' => ['nullable', 'string', 'max:255'],
            'width' => ['nullable', 'integer', 'min:0'],
            'height' => ['nullable', 'integer', 'min:0'],
            'sizeBytes' => ['nullable', 'integer', 'min:0'],
            'date' => ['nullable', 'date'],
            'md5' => ['nullable', 'string', 'size:32'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $entry = Entry::where('id', $validated['entryId'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // The key must live under this user's prefix — otherwise a crafted
        // request could attach someone else's object.
        abort_unless(
            str_starts_with($validated['key'], sprintf('media/%d/', $request->user()->id)),
            403,
            'Kľúč nepatrí tomuto používateľovi.'
        );

        abort_unless(
            Storage::disk('r2')->exists($validated['key']),
            422,
            'Súbor sa v R2 nenašiel — upload zrejme neprebehol.'
        );

        $media = $entry->media()->create([
            'id' => $validated['id'] ?? null,
            'user_id' => $request->user()->id,
            'kind' => $validated['kind'],
            'r2_key' => $validated['key'],
            'mime' => $validated['mime'] ?? null,
            'width' => $validated['width'] ?? null,
            'height' => $validated['height'] ?? null,
            'size_bytes' => $validated['sizeBytes'] ?? Storage::disk('r2')->size($validated['key']),
            'captured_at' => $validated['date'] ?? null,
            'md5' => $validated['md5'] ?? null,
            'position' => $validated['position'] ?? $entry->media()->count(),
        ]);

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    /**
     * Fresh signed URLs for media the app already knows about.
     *
     * Download URLs expire, so the client re-requests them in one batch rather
     * than one call per photo when a cached URL goes stale.
     */
    public function urls(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['uuid'],
        ]);

        $urls = Media::whereIn('id', $validated['ids'])
            ->where('user_id', $request->user()->id)
            ->get()
            ->mapWithKeys(fn (Media $media) => [$media->id => $media->downloadUrl()]);

        return response()->json([
            'urls' => $urls,
            'expiresIn' => (int) config('filesystems.disks.r2.download_ttl'),
        ]);
    }

    /**
     * Soft delete the row and drop the object.
     *
     * The object goes immediately because R2 storage is the thing being paid
     * for; the row stays so the deletion propagates to other devices.
     */
    public function destroy(Request $request, Media $medium): JsonResponse
    {
        abort_unless($medium->user_id === $request->user()->id, 404);

        if ($medium->r2_key) {
            Storage::disk('r2')->delete($medium->r2_key);
        }

        $medium->update(['r2_key' => null]);
        $medium->delete();

        return response()->json(status: 204);
    }
}
