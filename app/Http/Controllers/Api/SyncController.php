<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryRequest;
use App\Http\Resources\EntryResource;
use App\Http\Resources\TemplateResource;
use App\Models\Entry;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Local-first sync.
 *
 * The app owns the data and works offline; the server is the shared copy.
 * Conflicts are resolved last-write-wins on `updated_at` — with a single user
 * on one or two devices, a genuine simultaneous edit of the same entry is rare
 * enough that anything more elaborate would cost more than it saves.
 *
 * Soft-deleted rows are included in pulls so a deletion made on one device
 * actually reaches the other.
 */
class SyncController extends Controller
{
    /** Everything that changed since the client's last successful pull. */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['sometimes', 'date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $since = isset($validated['since']) ? Carbon::parse($validated['since']) : null;
        // Query strings arrive as strings; the strict comparison against the
        // row count below would never match without this cast.
        $limit = (int) ($validated['limit'] ?? 500);

        // Read the clock before querying: anything written during this request
        // must land in the *next* pull, never fall in the gap between them.
        $serverTime = now();

        $entries = $request->user()->entries()
            ->withTrashed()
            ->with(['media' => fn ($query) => $query->withTrashed()])
            ->when($since, fn ($query) => $query->where('updated_at', '>', $since))
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $templates = $request->user()->templates()
            ->withTrashed()
            ->when($since, fn ($query) => $query->where('updated_at', '>', $since))
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'serverTime' => $serverTime->toIso8601String(),
            'entries' => EntryResource::collection($entries),
            'templates' => TemplateResource::collection($templates),
            // True when the limit was hit — the client should pull again
            // straight away rather than wait for the next interval.
            'hasMore' => $entries->count() === $limit || $templates->count() === $limit,
        ]);
    }

    /**
     * Push locally-changed records.
     *
     * Wrapped in a transaction so a batch either lands whole or not at all —
     * a half-applied push would leave the client's cursor lying about what the
     * server holds.
     */
    public function push(Request $request): JsonResponse
    {
        // Every field has to be declared: validate() returns only what it was
        // told about, so anything missing here would be silently dropped from
        // the payload before it ever reached the database.
        $validated = $request->validate([
            'entries' => ['sometimes', 'array', 'max:500'],
            'entries.*.id' => ['required', 'uuid'],
            'entries.*.updatedAt' => ['required', 'date'],
            'entries.*.deletedAt' => ['nullable', 'date'],
            'entries.*.date' => ['required_without:entries.*.deletedAt', 'date'],
            'entries.*.text' => ['nullable', 'string'],
            'entries.*.templateId' => ['nullable', 'uuid'],
            'entries.*.templateName' => ['nullable', 'string', 'max:255'],
            'entries.*.sections' => ['nullable', 'array'],
            'entries.*.sections.*.fieldId' => ['required', 'string', 'max:255'],
            'entries.*.sections.*.label' => ['required', 'string', 'max:500'],
            'entries.*.sections.*.value' => ['present', 'string'],
            'entries.*.location' => ['nullable', 'array'],
            'entries.*.location.latitude' => ['required_with:entries.*.location', 'numeric', 'between:-90,90'],
            'entries.*.location.longitude' => ['required_with:entries.*.location', 'numeric', 'between:-180,180'],
            'entries.*.location.placeName' => ['nullable', 'string', 'max:255'],
            'entries.*.location.localityName' => ['nullable', 'string', 'max:255'],
            'entries.*.location.administrativeArea' => ['nullable', 'string', 'max:255'],
            'entries.*.location.country' => ['nullable', 'string', 'max:255'],
            'entries.*.location.altitude' => ['nullable', 'numeric'],
            'entries.*.location.auto' => ['nullable', 'boolean'],
            'entries.*.weather' => ['nullable', 'array'],
            'entries.*.tags' => ['nullable', 'array'],
            'entries.*.tags.*' => ['string', 'max:100'],
            'entries.*.starred' => ['nullable', 'boolean'],
            'entries.*.meta' => ['nullable', 'array'],

            'templates' => ['sometimes', 'array', 'max:100'],
            'templates.*.id' => ['required', 'uuid'],
            'templates.*.updatedAt' => ['required', 'date'],
            'templates.*.deletedAt' => ['nullable', 'date'],
            'templates.*.name' => ['required_without:templates.*.deletedAt', 'string', 'max:255'],
            'templates.*.description' => ['nullable', 'string', 'max:500'],
            'templates.*.icon' => ['nullable', 'string', 'max:100'],
            'templates.*.fields' => ['nullable', 'array'],
            'templates.*.fields.*.id' => ['required', 'string', 'max:255'],
            'templates.*.fields.*.label' => ['required', 'string', 'max:500'],
            'templates.*.fields.*.multiline' => ['nullable', 'boolean'],
            'templates.*.fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'templates.*.builtIn' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $applied = ['entries' => 0, 'templates' => 0, 'skipped' => 0];

        DB::transaction(function () use ($validated, $user, &$applied) {
            // Templates first: an entry written offline under a brand new
            // template arrives in the same batch as that template, and
            // entries.template_id is a foreign key. The other order fails.
            foreach ($validated['templates'] ?? [] as $payload) {
                $applied[$this->applyTemplate($user, $payload) ? 'templates' : 'skipped']++;
            }

            foreach ($validated['entries'] ?? [] as $payload) {
                $applied[$this->applyEntry($user, $payload) ? 'entries' : 'skipped']++;
            }
        });

        return response()->json([
            'serverTime' => now()->toIso8601String(),
            'applied' => $applied,
        ]);
    }

    /** @return bool whether the record was written (false = server copy was newer) */
    private function applyEntry($user, array $payload): bool
    {
        $existing = Entry::withTrashed()
            ->where('id', $payload['id'])
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->updated_at >= Carbon::parse($payload['updatedAt'])) {
            return false;
        }

        if (! empty($payload['deletedAt'])) {
            $existing?->delete();

            return (bool) $existing;
        }

        $attributes = $this->entryAttributes($payload);

        if ($existing) {
            $existing->restore();
            $existing->update($attributes);
        } else {
            $user->entries()->create($attributes + ['id' => $payload['id']]);
        }

        return true;
    }

    private function applyTemplate($user, array $payload): bool
    {
        $existing = Template::withTrashed()
            ->where('id', $payload['id'])
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->updated_at >= Carbon::parse($payload['updatedAt'])) {
            return false;
        }

        if (! empty($payload['deletedAt'])) {
            $existing?->delete();

            return (bool) $existing;
        }

        $attributes = [
            'name' => $payload['name'] ?? 'Bez názvu',
            'description' => $payload['description'] ?? null,
            'icon' => $payload['icon'] ?? 'sunny-outline',
            'fields' => $payload['fields'] ?? [],
            'built_in' => $payload['builtIn'] ?? false,
        ];

        if ($existing) {
            $existing->restore();
            $existing->update($attributes);
        } else {
            $user->templates()->create($attributes + ['id' => $payload['id']]);
        }

        return true;
    }

    /**
     * Same wire-format flattening as {@see EntryRequest::toAttributes()}, but
     * for records arriving inside a batch rather than as the request body.
     */
    private function entryAttributes(array $payload): array
    {
        $location = $payload['location'] ?? null;
        [$instant, $offset] = Entry::splitTimestamp($payload['date'] ?? now()->toIso8601String());

        return [
            'entry_date' => $instant,
            'entry_utc_offset' => $offset,
            'text' => $payload['text'] ?? '',
            'template_id' => $payload['templateId'] ?? null,
            'template_name' => $payload['templateName'] ?? null,
            'sections' => $payload['sections'] ?? null,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
            'place_name' => $location['placeName'] ?? null,
            'locality_name' => $location['localityName'] ?? null,
            'administrative_area' => $location['administrativeArea'] ?? null,
            'country' => $location['country'] ?? null,
            'altitude' => $location['altitude'] ?? null,
            'location_auto' => $location['auto'] ?? true,
            'weather' => $payload['weather'] ?? null,
            'tags' => $payload['tags'] ?? [],
            'starred' => $payload['starred'] ?? false,
            'meta' => $payload['meta'] ?? null,
        ];
    }
}
