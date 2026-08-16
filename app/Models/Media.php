<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class Media extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /** Laravel would otherwise pluralise this to "medias". */
    protected $table = 'media';

    protected $fillable = [
        'id',
        'entry_id',
        'kind',
        'r2_key',
        'mime',
        'size_bytes',
        'width',
        'height',
        'captured_at',
        'md5',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }

    /**
     * Short-lived signed URL the app fetches the file through.
     *
     * Null until the upload is confirmed, so the client can tell "still
     * uploading" apart from "broken".
     *
     * Signing failures are swallowed on purpose. This is called once per photo
     * while serving a sync page, and letting one bad row throw would fail the
     * whole pull — the client would lose the entire journal over a single
     * unreachable file.
     */
    public function downloadUrl(): ?string
    {
        if (! $this->r2_key) {
            return null;
        }

        try {
            return Storage::disk('r2')->temporaryUrl(
                $this->r2_key,
                now()->addSeconds((int) config('filesystems.disks.r2.download_ttl'))
            );
        } catch (Throwable $e) {
            Log::warning('[lumen] nepodarilo sa podpísať URL', [
                'media' => $this->id,
                'key' => $this->r2_key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
