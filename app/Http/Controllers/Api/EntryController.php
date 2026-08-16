<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryRequest;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EntryController extends Controller
{
    /**
     * Newest first, paginated.
     *
     * `media` is eager-loaded — without it a page of 50 entries would sign
     * URLs across 50 extra queries.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'search' => ['sometimes', 'string', 'max:255'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        // Postgres needs ILIKE for case-insensitive matching; SQLite's LIKE is
        // already case-insensitive and does not know the operator at all.
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $needle = '%'.$request->string('search').'%';

        $entries = $request->user()->entries()
            ->with('media')
            ->when($request->filled('search'), fn ($query) => $query->where(
                fn ($q) => $q->where('text', $like, $needle)
                    ->orWhere('place_name', $like, $needle)
                    ->orWhere('locality_name', $like, $needle)
            ))
            ->when($request->filled('from'), fn ($query) => $query->where('entry_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('entry_date', '<=', $request->date('to')))
            ->orderByDesc('entry_date')
            ->paginate($request->integer('per_page', 50));

        return EntryResource::collection($entries);
    }

    public function show(Request $request, Entry $entry): EntryResource
    {
        $this->authorizeOwnership($request, $entry);

        return new EntryResource($entry->load('media'));
    }

    public function store(EntryRequest $request): JsonResponse
    {
        $entry = $request->user()->entries()->create(
            $request->toAttributes() + ['id' => $request->input('id')]
        );

        return (new EntryResource($entry->load('media')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(EntryRequest $request, Entry $entry): EntryResource
    {
        $this->authorizeOwnership($request, $entry);

        $entry->update($request->toAttributes());

        return new EntryResource($entry->load('media'));
    }

    /**
     * Soft delete — the row has to survive so the sync can tell other devices
     * the entry is gone. Its media cascades the same way.
     */
    public function destroy(Request $request, Entry $entry): JsonResponse
    {
        $this->authorizeOwnership($request, $entry);

        $entry->media()->delete();
        $entry->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, Entry $entry): void
    {
        abort_unless($entry->user_id === $request->user()->id, 404);
    }
}
