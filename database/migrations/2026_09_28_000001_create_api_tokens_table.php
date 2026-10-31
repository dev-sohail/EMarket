<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('name', 255);
            $table->string('user_id', 36)->nullable();
            $table->string('token', 64);
            $table->string('tokenable_type', 255)->nullable();
            $table->string('tokenable_id', 36)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('token', 'api_tokens_token_unique');
            $table->index(['tokenable_type', 'tokenable_id'], 'api_tokens_tokenable_index');
            $table->index(['user_id', 'is_active'], 'api_tokens_user_active_index');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('api_tokens');
        Schema::enableForeignKeyConstraints();
    }
};
