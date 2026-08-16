<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The journal entries.
 *
 * Columns mirror `types/index.ts` in the app and stay a superset of the Day One
 * CSV export, so importing loses nothing and a future export round-trips.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The moment the entry is *about* — user-editable, defaults to the
            // time of writing. Distinct from created_at, which never moves.
            // Stored as a true UTC instant so ordering is correct worldwide.
            $table->timestampTz('entry_date');

            // Minutes east of UTC at the moment of writing (+120 for Slovak
            // summer time). Without it the instant alone cannot reproduce the
            // wall-clock time the entry was written at — a journal written at
            // 21:30 in Bratislava must always read 21:30, not 19:30, and must
            // keep reading 21:30 after a trip to another timezone.
            $table->smallInteger('entry_utc_offset')->default(0);

            $table->text('text');

            // Nulled rather than cascaded: deleting a template must not delete
            // the years of entries written under it.
            $table->foreignUuid('template_id')->nullable()
                ->constrained('templates')->nullOnDelete();
            $table->string('template_name')->nullable();
            $table->json('sections')->nullable();

            // Location, flattened so the map can query it without unpacking JSON.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('place_name')->nullable();
            $table->string('locality_name')->nullable();
            $table->string('administrative_area')->nullable();
            $table->string('country')->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->boolean('location_auto')->default(true);

            $table->json('weather')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('starred')->default(false);

            // Day One fields we keep verbatim (uuid, timezone, device, …).
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The journal list: newest first, for one user.
            $table->index(['user_id', 'entry_date']);
            // The sync endpoint: everything touched since a timestamp.
            $table->index(['user_id', 'updated_at']);
            // The map: only entries that actually have coordinates.
            $table->index(['user_id', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
