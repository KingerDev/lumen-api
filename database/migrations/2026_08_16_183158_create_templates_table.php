<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt sets an entry can be written under.
 *
 * `fields` stays JSON rather than its own table: the app treats a template as
 * one editable document, questions are reordered as a unit, and nothing ever
 * queries a single question. A join table would buy nothing here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            // Client-generated UUIDs — the app creates records offline and the
            // id must survive the sync unchanged.
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon')->default('sunny-outline');
            $table->json('fields');
            $table->boolean('built_in')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Sync pulls "everything changed since X" for one user.
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
