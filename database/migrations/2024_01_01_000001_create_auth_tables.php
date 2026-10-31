<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->char('email', 255)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->char('password', 255);
            $table->char('phone', 20)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->char('avatar', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->char('remember_token', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('is_active', 'users_is_active_index');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255)->unique();
            $table->char('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index('is_system', 'roles_is_system_index');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255)->unique();
            $table->char('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->char('group_name', 255)->nullable();
            $table->timestamps();
            $table->index('group_name', 'permissions_group_name_index');
        });

        Schema::create('user_role', function (Blueprint $table) {
            $table->string('user_id', 36);
            $table->string('role_id', 36);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index('user_id', 'user_role_user_id_index');
            $table->index('role_id', 'user_role_role_id_index');
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->string('role_id', 36);
            $table->string('permission_id', 36);
            $table->timestamps();
            $table->index('role_id', 'role_permission_role_id_index');
            $table->index('permission_id', 'role_permission_permission_id_index');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            $table->index('email', 'password_reset_tokens_email_index');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity');
            $table->index('user_id', 'sessions_user_id_index');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
            $table->index('failed_at', 'failed_jobs_failed_at_index');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
            $table->index('queue', 'jobs_queue_index');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->longText('options')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->index('name', 'job_batches_name_index');
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value');
            $table->integer('expiration');
            $table->index('expiration', 'cache_expiration_index');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // `owner` is the column Illuminate\Cache\DatabaseLock writes. This
            // used to be `owner_id` (NOT NULL, no default), which rejected every
            // lock insert under STRICT_TRANS_TABLES and made Cache::lock() — the
            // checkout and Stripe webhook mutexes — permanently unacquirable.
            $table->string('owner');
            $table->integer('expiration');
            $table->index('expiration', 'cache_locks_expiration_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
