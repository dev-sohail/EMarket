<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the `settings` table the application already expects.
 *
 * The Setting model, a SettingSeeder, an admin SettingController and the
 * `setting()` helper all exist, but no migration ever created the table. Every
 * `setting()` call therefore caught "no such table: settings" and returned its
 * default, so the whole settings feature was inert: store name, currency,
 * timezone, tax rate, shipping cost and the rest all silently fell back.
 *
 * `key` is unique because the helper resolves a setting by
 * `pluck('value', 'key')`, which keeps only the last row for a duplicated key.
 * `group` is indexed because the admin settings screen and any future
 * grouped view read by it.
 *
 * `value` is a nullable text column rather than a string so an operator can
 * paste a paragraph (meta descriptions, announcement text) without truncation.
 * The model's `mixed` cast handles encoding.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('group', 100)->index();
            $table->string('key', 191)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
