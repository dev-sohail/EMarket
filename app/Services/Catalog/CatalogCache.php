<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;

/**
 * Central place for the storefront category/brand cache keys used across
 * header/footer/shop/search/category views, so every write path invalidates
 * the same keys the read paths use.
 */
class CatalogCache
{
    public static function forgetCategories(): void
    {
        Cache::forget('categories.nav');
        Cache::forget('categories.mobile');
        Cache::forget('categories.shop');
        Cache::forget('categories.search');
    }

    public static function forgetBrands(): void
    {
        Cache::forget('brands.shop');
        Cache::forget('brands.search');
    }

    /**
     * Every key here is built from a prefixed string id (`cat_…`), never an
     * integer, so the scalar arm has to accept a string: the previous `int`
     * hint turned CategoryService::delete() and toggleStatus() into a TypeError
     * whenever they passed their own `$category->getKey()`, which is what both
     * of those write paths do.
     */
    public static function forgetCategoryBrands(string|int|iterable $categoryIds): void
    {
        $ids = is_iterable($categoryIds) ? $categoryIds : [$categoryIds];

        foreach ($ids as $id) {
            Cache::forget('category.brands.'.$id);
        }
    }
}
