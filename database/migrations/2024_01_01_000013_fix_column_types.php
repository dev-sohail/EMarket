<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix sessions table user_id to be string (from fixup migrations)
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->string('user_id', 255)->nullable()->change();
            });
        }

        // Fix inventory_movements performed_by and reference_id to string
        if (Schema::hasTable('inventory_movements')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->string('performed_by', 255)->nullable()->change();
                $table->string('reference_id', 255)->nullable()->change();
            });
        }

        // Fix audit_logs entity_id to string
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('entity_id', 255)->nullable()->change();
            });
        }

        // Fix return_status_histories return_id to string
        if (Schema::hasTable('return_status_histories')) {
            Schema::table('return_status_histories', function (Blueprint $table) {
                $table->string('return_id', 255)->change();
            });
        }

        // Fix tax_rule_targets target_id to string nullable
        if (Schema::hasTable('tax_rule_targets')) {
            Schema::table('tax_rule_targets', function (Blueprint $table) {
                $table->string('target_id', 255)->nullable()->change();
            });
        }

        // Fix notifications notifiable_id to string
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('notifiable_id', 255)->change();
            });
        }

        // Fix popups content to nullable text (was already done in popup creation)
        // But ensuring it's correct
        if (Schema::hasTable('popups')) {
            Schema::table('popups', function (Blueprint $table) {
                $table->text('content')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Reverse the changes
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('inventory_movements')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->unsignedBigInteger('performed_by')->nullable()->change();
                $table->unsignedBigInteger('reference_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('entity_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('return_status_histories')) {
            Schema::table('return_status_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('return_id')->change();
            });
        }

        if (Schema::hasTable('tax_rule_targets')) {
            Schema::table('tax_rule_targets', function (Blueprint $table) {
                $table->unsignedBigInteger('target_id')->nullable()->change();
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('notifiable_id')->change();
            });
        }

        if (Schema::hasTable('popups')) {
            Schema::table('popups', function (Blueprint $table) {
                $table->text('content')->nullable(false)->change();
            });
        }
    }
};
