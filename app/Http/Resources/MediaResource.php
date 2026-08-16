<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Media */
class MediaResource extends JsonResource
{
    /**
     * Field names match `Media` in the app's types/index.ts so the client can
     * drop the payload straight into its store.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entryId' => $this->entry_id,
            'kind' => $this->kind,
            'remoteKey' => $this->r2_key,
            // Signed, short-lived. The app must not persist this.
            'url' => $this->downloadUrl(),
            'mime' => $this->mime,
            'sizeBytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'date' => $this->captured_at?->toIso8601String(),
            'md5' => $this->md5,
            'position' => $this->position,
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'deletedAt' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
