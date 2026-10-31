<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_definitions', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->text('description')->nullable();
            $table->string('source', 64)->default('manual'); // manual, reflection
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'slug'], 'api_definitions_active_slug_index');
        });

        Schema::create('api_endpoints', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('api_definition_id', 36)->nullable();
            $table->string('method', 10); // GET, POST, PUT, PATCH, DELETE
            $table->string('path', 500);
            $table->string('action', 255)->nullable(); // controller@method or service method
            $table->string('handler_type', 32)->default('controller'); // controller, service, closure
            $table->text('description')->nullable();
            $table->boolean('requires_auth')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // deleted_at participates in the unique key so a retired endpoint
            // frees its method+path for a replacement; MySQL treats the NULL of
            // a live row as distinct, which is exactly the behaviour wanted.
            $table->unique(['method', 'path', 'deleted_at'], 'api_endpoints_method_path_unique');
            $table->index(['api_definition_id', 'is_active'], 'api_endpoints_definition_active_index');
        });

        Schema::create('api_endpoint_parameters', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('api_endpoint_id', 36)->nullable();
            $table->string('name', 255);
            $table->string('location', 16); // query, body, path, header
            $table->string('type', 32)->default('string'); // string, integer, number, boolean, array, file
            $table->boolean('required')->default(false);
            $table->text('default_value')->nullable();
            $table->text('description')->nullable();
            $table->longText('rules')->nullable(); // JSON: validation rules
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['api_endpoint_id', 'location'], 'api_endpoint_params_endpoint_location_index');
        });

        Schema::create('api_endpoint_permissions', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('api_endpoint_id', 36)->nullable();
            $table->string('permission_id', 36)->nullable();
            $table->string('access_type', 32)->default('allow'); // allow, deny
            $table->text('conditions')->nullable(); // JSON: ABAC conditions
            $table->timestamps();

            $table->unique(['api_endpoint_id', 'permission_id'], 'api_endpoint_permission_unique');
            $table->index('permission_id', 'api_endpoint_perms_permission_index');
        });

        Schema::create('api_route_logs', function (Blueprint $table) {
            $table->string('_id', 36)->primary();
            $table->string('api_endpoint_id', 36)->nullable();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->unsignedBigInteger('status_code')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('user_id', 36)->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['api_endpoint_id', 'created_at'], 'api_route_logs_endpoint_created_index');
            $table->index(['method', 'path'], 'api_route_logs_method_path_index');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('api_route_logs');
        Schema::dropIfExists('api_endpoint_permissions');
        Schema::dropIfExists('api_endpoint_parameters');
        Schema::dropIfExists('api_endpoints');
        Schema::dropIfExists('api_definitions');

        Schema::enableForeignKeyConstraints();
    }
};
