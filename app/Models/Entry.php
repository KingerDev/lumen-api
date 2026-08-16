<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Entry extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'entry_date',
        'entry_utc_offset',
        'text',
        'template_id',
        'template_name',
        'sections',
        'latitude',
        'longitude',
        'place_name',
        'locality_name',
        'administrative_area',
        'country',
        'altitude',
        'location_auto',
        'weather',
        'tags',
        'starred',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'datetime',
            'entry_utc_offset' => 'integer',
            'sections' => 'array',
            'weather' => 'array',
            'tags' => 'array',
            'meta' => 'array',
            'starred' => 'boolean',
            'location_auto' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'altitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('position');
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The entry's timestamp back in the offset it was written at.
     *
     * This — not the raw UTC instant — is what the app and the user see, so a
     * 21:30 entry keeps reading 21:30 from any device in any timezone.
     */
    public function localDate(): ?Carbon
    {
        return $this->entry_date?->copy()->utcOffset($this->entry_utc_offset ?? 0);
    }

    /**
     * Splits an incoming ISO-8601 string into the instant to store and the
     * offset to remember.
     *
     * @return array{0: Carbon, 1: int} [utc instant, offset in minutes]
     */
    public static function splitTimestamp(string $iso): array
    {
        $parsed = Carbon::parse($iso);

        return [$parsed->copy()->utc(), $parsed->utcOffset()];
    }
}
