<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos and videos.
 *
 * Only metadata lives here — the bytes are in R2 under `r2_key`. The server
 * never touches the file itself, it just signs URLs the app uploads to and
 * downloads from directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('entry_id')->constrained('entries')->cascadeOnDelete();

            $table->enum('kind', ['photo', 'video'])->default('photo');

            // Object key in the R2 bucket, e.g. "media/<user>/<uuid>.jpg".
            // Null while the row exists but the upload has not been confirmed.
            $table->string('r2_key')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestampTz('captured_at')->nullable();

            // Day One's md5 filename stem — the join key when importing the
            // export, and what stops a re-run from duplicating files.
            $table->string('md5', 32)->nullable();

            // Ordering within an entry, so the "cover" photo stays the cover.
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['entry_id', 'position']);
            $table->index(['user_id', 'updated_at']);
            $table->unique(['user_id', 'md5']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
