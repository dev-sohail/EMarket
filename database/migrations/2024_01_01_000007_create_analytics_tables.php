<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Analytics tables
        Schema::create('analytics_visitors', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('uuid', 36)->unique();
            $table->string('country', 2)->nullable();
            $table->string('device_type', 255)->nullable();
            $table->string('browser', 255)->nullable();
            $table->string('referrer_domain', 255)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index('country', 'analytics_visitors_country_index');
            $table->index('device_type', 'analytics_visitors_device_type_index');
            $table->index('referrer_domain', 'analytics_visitors_referrer_domain_index');
        });

        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('visitor_id', 36)->nullable();
            $table->char('session_uuid', 255)->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('page_view_count')->default(0);
            $table->integer('duration_seconds')->default(0);
            $table->boolean('bounced')->default(false);
            $table->timestamps();
            $table->index('ended_at', 'analytics_sessions_ended_at_index');
            $table->index('session_uuid', 'analytics_sessions_session_uuid_index');
            $table->foreign('visitor_id', 'analytics_sessions_visitor_id_foreign')
                ->references('_id')->on('analytics_visitors')
                ->onDelete('CASCADE');
        });

        Schema::create('analytics_page_views', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('visitor_id', 36)->nullable();
            $table->char('session_uuid', 255);
            $table->char('path', 255);
            $table->char('title', 255)->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->timestamps();
            $table->index('visitor_id', 'analytics_page_views_visitor_id_foreign');
            $table->index('path', 'analytics_page_views_path_index');
            $table->index('viewed_at', 'analytics_page_views_viewed_at_index');
            $table->index('session_uuid', 'analytics_page_views_session_uuid_index');
            $table->foreign('visitor_id', 'analytics_page_views_visitor_id_foreign')
                ->references('_id')->on('analytics_visitors')
                ->onDelete('SET NULL');
        });

        Schema::create('analytics_conversions', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('visitor_id', 36)->nullable();
            $table->char('session_uuid', 255)->nullable();
            $table->string('order_id', 36)->nullable();
            $table->string('customer_id', 36)->nullable();
            $table->decimal('revenue', 12, 2)->default(0);
            $table->char('conversion_type', 255)->default('order');
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->index('visitor_id', 'analytics_conversions_visitor_id_foreign');
            $table->index('order_id', 'analytics_conversions_order_id_foreign');
            $table->index('customer_id', 'analytics_conversions_customer_id_foreign');
            $table->index('converted_at', 'analytics_conversions_converted_at_index');
            $table->index('session_uuid', 'analytics_conversions_session_uuid_index');
            $table->foreign('visitor_id', 'analytics_conversions_visitor_id_foreign')
                ->references('_id')->on('analytics_visitors')
                ->onDelete('SET NULL');
            $table->foreign('customer_id', 'analytics_conversions_customer_id_foreign')
                ->references('_id')->on('customers')
                ->onDelete('SET NULL');
            $table->foreign('order_id', 'analytics_conversions_order_id_foreign')
                ->references('_id')->on('orders')
                ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_conversions');
        Schema::dropIfExists('analytics_page_views');
        Schema::dropIfExists('analytics_sessions');
        Schema::dropIfExists('analytics_visitors');
    }
};
