<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;

class FlashSaleController extends Controller
{
    public function index()
    {
        $flashSales = FlashSale::active()
            ->with(['products' => function ($q) {
                $q->where('is_active', true)->with('images');
            }])
            ->orderBy('starts_at', 'desc')
            ->get();

        return view('store.flash-sales.index', compact('flashSales'));
    }

    public function show(FlashSale $flashSale)
    {
        $flashSale->load(['products' => function ($q) {
            $q->where('is_active', true)->with('images');
        }]);

        return view('store.flash-sales.show', compact('flashSale'));
    }
}
