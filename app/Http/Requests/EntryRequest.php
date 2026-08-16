<?php

namespace App\Http\Requests;

use App\Models\Entry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an entry as the app sends it (camelCase, nested `location`) and
 * flattens it into column names.
 *
 * Keeping the translation here means the controllers never see the wire format
 * and the app never has to learn the database's shape.
 */
class EntryRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'id' => ['sometimes', 'uuid'],
            'date' => [$required, 'date'],
            'text' => [$required, 'string'],

            'templateId' => ['nullable', 'uuid', Rule::exists('templates', 'id')
                ->where('user_id', $this->user()->id)],
            'templateName' => ['nullable', 'string', 'max:255'],

            'sections' => ['nullable', 'array'],
            'sections.*.fieldId' => ['required', 'string', 'max:255'],
            'sections.*.label' => ['required', 'string', 'max:500'],
            'sections.*.value' => ['present', 'string'],

            'location' => ['nullable', 'array'],
            'location.latitude' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required_with:location', 'numeric', 'between:-180,180'],
            'location.placeName' => ['nullable', 'string', 'max:255'],
            'location.localityName' => ['nullable', 'string', 'max:255'],
            'location.administrativeArea' => ['nullable', 'string', 'max:255'],
            'location.country' => ['nullable', 'string', 'max:255'],
            'location.altitude' => ['nullable', 'numeric'],
            'location.auto' => ['nullable', 'boolean'],

            'weather' => ['nullable', 'array'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'starred' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ];
    }

    /** Wire format → column names. */
    public function toAttributes(): array
    {
        $attributes = [];

        // The instant and the offset it was written at are stored separately —
        // see Entry::splitTimestamp().
        if ($this->has('date')) {
            [$instant, $offset] = Entry::splitTimestamp($this->input('date'));
            $attributes['entry_date'] = $instant;
            $attributes['entry_utc_offset'] = $offset;
        }

        // Only touch what was actually sent, so PATCH stays partial.
        $map = [
            'text' => 'text',
            'templateId' => 'template_id',
            'templateName' => 'template_name',
            'sections' => 'sections',
            'weather' => 'weather',
            'tags' => 'tags',
            'starred' => 'starred',
            'meta' => 'meta',
        ];

        foreach ($map as $input => $column) {
            if ($this->has($input)) {
                $attributes[$column] = $this->input($input);
            }
        }

        if ($this->has('location')) {
            $location = $this->input('location');

            // An explicit null means "this entry has no place any more".
            $attributes += [
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'place_name' => $location['placeName'] ?? null,
                'locality_name' => $location['localityName'] ?? null,
                'administrative_area' => $location['administrativeArea'] ?? null,
                'country' => $location['country'] ?? null,
                'altitude' => $location['altitude'] ?? null,
                'location_auto' => $location['auto'] ?? true,
            ];
        }

        return $attributes;
    }
}
