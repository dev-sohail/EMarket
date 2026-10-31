<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('title', 255);
            $table->char('subtitle', 255)->nullable();
            $table->text('description')->nullable();
            $table->char('image', 255)->nullable();
            $table->char('color', 255)->nullable();
            $table->char('url', 255)->nullable();
            $table->char('url_2', 255)->nullable();
            $table->char('cta_text', 255)->nullable();
            $table->char('cta_text_2', 255)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->enum('position', ['homepage', 'promotional', 'footer', 'sale'])->default('homepage');
            $table->timestamps();
            $table->index('is_active', 'banners_is_active_index');
            $table->index('sort_order', 'banners_sort_order_index');
            $table->index('position', 'banners_position_index');
            $table->index('starts_at', 'banners_starts_at_index');
            $table->index('ends_at', 'banners_ends_at_index');
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('title', 255);
            $table->char('slug', 255)->unique();
            $table->text('content')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->char('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->char('og_title', 255)->nullable();
            $table->text('og_description')->nullable();
            $table->char('og_image', 255)->nullable();
            $table->char('canonical_url', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index('status', 'pages_status_index');
            $table->index('sort_order', 'pages_sort_order_index');
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('title', 255);
            $table->char('slug', 255)->unique();
            $table->text('content');
            $table->text('excerpt')->nullable();
            $table->char('featured_image', 255)->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->boolean('show_on_site')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('views')->default(0);
            $table->string('author_id', 36)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->char('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('author_id', 'blog_posts_author_id_index');
            $table->index('status', 'blog_posts_status_index');
            $table->index('published_at', 'blog_posts_published_at_index');
            $table->index('slug', 'blog_posts_slug_index');
            $table->index('show_on_site', 'blog_posts_show_on_site_index');
            $table->index('is_featured', 'blog_posts_is_featured_index');
            $table->index('views', 'blog_posts_views_index');
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('question', 255);
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->char('name', 255);
            $table->char('email', 255);
            $table->char('subject', 255);
            $table->text('message');
            $table->enum('status', ['new', 'read', 'replied', 'closed'])->default('new');
            $table->text('admin_notes')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('user_id', 36)->nullable();
            $table->timestamps();
            $table->index('user_id', 'enquiries_user_id_index');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('user_id', 36)->nullable();
            $table->char('action', 255);
            $table->char('entity_type', 255);
            $table->string('entity_id', 255)->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->char('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index('user_id', 'audit_logs_user_id_index');
            $table->index('entity_type', 'audit_logs_entity_type_index');
            $table->index('entity_id', 'audit_logs_entity_id_index');
            $table->index('action', 'audit_logs_action_index');
            $table->index('created_at', 'audit_logs_created_at_index');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('type', 255)->nullable();
            $table->string('notifiable_id', 255);
            $table->char('notifiable_type', 255);
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
        });

        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('event_id')->unique();
            $table->string('type');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliates', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('customer_id', 36)->nullable();
            $table->char('code', 16)->unique();
            $table->char('status', 20)->default('active');
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->index('customer_id', 'affiliates_customer_id_index');
            $table->index(['status', 'created_at'], 'affiliates_status_created_at_index');
            $table->index('status', 'affiliates_status_index');
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('affiliate_id', 36)->nullable();
            $table->string('order_id', 36)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('rate', 5, 2)->default(0);
            $table->char('status', 20)->default('pending');
            $table->char('reference', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['affiliate_id', 'order_id'], 'affiliate_commissions_affiliate_order_unique');
            $table->index('order_id', 'affiliate_commissions_order_id_index');
            $table->index('status', 'affiliate_commissions_status_index');
            $table->index(['status', 'created_at'], 'affiliate_commissions_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('stripe_webhook_events');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('enquiries');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('banners');
    }
};
