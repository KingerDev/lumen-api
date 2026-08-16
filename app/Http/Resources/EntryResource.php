<?php

namespace App\Http\Resources;

use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Entry */
class EntryResource extends JsonResource
{
    /**
     * Shaped to match `Entry` in the app's types/index.ts — camelCase keys and
     * a nested `location` object — so no translation layer is needed client side.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->localDate()?->toIso8601String(),
            'text' => $this->text,
            'templateId' => $this->template_id,
            'templateName' => $this->template_name,
            'sections' => $this->sections,
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'location' => $this->when($this->hasLocation(), fn () => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'placeName' => $this->place_name,
                'localityName' => $this->locality_name,
                'administrativeArea' => $this->administrative_area,
                'country' => $this->country,
                'altitude' => $this->altitude,
                'auto' => $this->location_auto,
            ]),
            'weather' => $this->weather,
            'tags' => $this->tags ?? [],
            'starred' => $this->starred,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'deletedAt' => $this->deleted_at?->toIso8601String(),
            'meta' => $this->meta,
        ];
    }
}
