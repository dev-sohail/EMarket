<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('name')->nullable();
            $table->string('type')->default('image');
            $table->string('position')->default('homepage');
            $table->string('size')->default('medium');
            $table->char('title', 255);
            $table->text('content')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('color')->nullable();
            $table->string('overlay_color')->nullable();
            $table->string('image')->nullable();
            $table->text('custom_html')->nullable();
            $table->string('url')->nullable();
            $table->string('cta_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('show_on_mobile')->default(true);
            $table->boolean('show_on_desktop')->default(true);
            $table->integer('show_close_button')->default(true);
            $table->integer('enable_cookie')->default(true);
            $table->integer('cookie_expiry_days')->default(7);
            $table->integer('show_only_once')->default(false);
            $table->integer('delay_seconds')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
    }
};
