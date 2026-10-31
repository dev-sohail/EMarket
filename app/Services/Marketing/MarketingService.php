<?php

namespace App\Services\Marketing;

use App\Models\Banner;
use App\Models\DiscountCampaign;
use App\Models\FlashSale;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class MarketingService
{
    public function getCampaigns(): Collection
    {
        return DiscountCampaign::with(['products', 'categories'])
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->get();
    }

    public function getActiveFlashSales(): Collection
    {
        return FlashSale::with(['products' => function ($q) {
            $q->with(['images', 'variants']);
        }])
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->get();
    }

    public function getBanners(string $position): Collection
    {
        return Banner::where('position', $position)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('sort_order')
            ->get();
    }

    public function getFeaturedProducts(int $limit = 8)
    {
        return Product::with(['images', 'variants'])
            ->published()
            ->featured()
            ->limit($limit)
            ->get();
    }

    public function getNewArrivals(int $limit = 8)
    {
        return Product::with(['images', 'variants'])
            ->published()
            ->new()
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getBestSellers(int $limit = 8)
    {
        return Product::with(['images', 'variants'])
            ->published()
            ->withSum('orderItems', 'quantity as total_sold')
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get();
    }
}
