<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign in with Apple.
 *
 * An Apple account has no password and may share no e-mail, and its stable identity is Apple's `sub`,
 * not the e-mail (which can be a private relay address or missing entirely).
 * The refresh token is kept only so the account can be revoked at Apple when
 * the user deletes it — App Review requires that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('apple_id')->nullable()->unique()->after('email');
            $table->text('apple_refresh_token')->nullable()->after('apple_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['apple_id']);
            $table->dropColumn(['apple_id', 'apple_refresh_token']);
            $table->string('password')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
