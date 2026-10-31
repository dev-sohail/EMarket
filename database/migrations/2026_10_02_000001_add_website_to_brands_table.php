<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The brand create and edit screens both render a Website field and
 * StoreBrandRequest/UpdateBrandRequest both validate it as a URL, but `brands`
 * had no such column and Brand::$fillable never listed it — so the field always
 * rendered empty on edit and any URL an operator typed was silently discarded on
 * save. The storefront does not read it yet; it is stored because the admin form
 * promises it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('website')->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('website');
        });
    }
};
