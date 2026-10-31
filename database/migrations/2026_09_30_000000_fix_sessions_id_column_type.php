<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give `sessions.id` the string type Laravel's session driver actually writes.
 *
 * The sessions table was created with `$table->id()`, which is an auto-incrementing
 * BIGINT UNSIGNED. Laravel's database session handler instead keys sessions by a
 * 40 character random string, so every session INSERT failed with
 * "Incorrect integer value ... for column `id`".
 *
 * Because no session row was ever written, the session cookie could not be
 * resolved on the next request, so a new session - and therefore a new CSRF
 * token - was minted every time. The form token and the session token could
 * never match, so every state-changing POST failed with 419 Page Expired.
 * That is why admin login (and every other form) appeared to do nothing.
 *
 * The failure was silent from the user's point of view: the pages rendered
 * normally and only the POST failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'id')) {
            return;
        }

        // Already correct, or a driver we cannot safely alter here.
        if (Schema::getColumnType('sessions', 'id') === 'string') {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            // MySQL refuses to drop the primary key while it is still the sole
            // AUTO_INCREMENT column, so clear that first. The intermediate
            // MODIFY also disarms the implicit NOT NULL/existing-default
            // assumptions so the type change is unambiguous.
            DB::statement('ALTER TABLE `sessions` MODIFY `id` BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE `sessions` DROP PRIMARY KEY');
            DB::statement('ALTER TABLE `sessions` MODIFY `id` VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE `sessions` ADD PRIMARY KEY (`id`)');

            return;
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->string('id', 255)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'id')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `sessions` DROP PRIMARY KEY');
            DB::statement('ALTER TABLE `sessions` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            DB::statement('ALTER TABLE `sessions` ADD PRIMARY KEY (`id`)');
        }
    }
};
