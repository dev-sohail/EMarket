<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\FlashSale;
use App\Services\Catalog\BrandService;
use App\Services\Catalog\CategoryService;
use App\Services\Catalog\ProductService;

class HomeController extends Controller
{
    public function __construct(
        protected ProductService $productService,
        protected CategoryService $categoryService,
        protected BrandService $brandService
    ) {}

    public function index()
    {
        $featuredProducts = $this->productService->getFeatured(8);
        $newArrivals = $this->productService->getNewArrivals(8);
        $bestSellers = $this->productService->getBestSellers(8);
        $categories = $this->categoryService->getTree();
        $brands = $this->brandService->getAll();

        $homepageBanners = Banner::active()->position('homepage')->orderBy('sort_order')->first();
        $promotionalBanners = Banner::active()->position('promotional')->orderBy('sort_order')->first();
        $saleBanners = Banner::active()->position('sale')->orderBy('sort_order')->first();
        $footerBanners = Banner::active()->position('footer')->orderBy('sort_order')->first();

        $flashSales = FlashSale::active()
            ->with(['products' => function ($q) {
                $q->where('is_active', true)->with('images')->limit(4);
            }])
            ->latest()
            ->limit(3)
            ->get();

        return view('store.home', compact(
            'featuredProducts',
            'newArrivals',
            'bestSellers',
            'categories',
            'brands',
            'homepageBanners',
            'promotionalBanners',
            'saleBanners',
            'footerBanners',
            'flashSales',
        ));
    }
}
