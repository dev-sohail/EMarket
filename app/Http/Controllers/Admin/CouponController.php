<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Models\Coupon;
use App\Services\Coupon\CouponService;

class CouponController extends Controller
{
    protected CouponService $couponService;

    public function __construct(CouponService $couponService)
    {
        $this->couponService = $couponService;
    }

    public function index()
    {
        $coupons = $this->couponService->getAllCoupons();

        return view('admin.coupon.index', compact('coupons'));
    }

    public function create()
    {
        $products = $this->couponService->getProductsForSelect();
        $categories = $this->couponService->getCategoriesForSelect();

        return view('admin.coupon.create', compact('products', 'categories'));
    }

    public function store(StoreCouponRequest $request)
    {
        $this->couponService->create($request->validated());

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon created successfully.');
    }

    public function edit(Coupon $coupon)
    {
        $products = $this->couponService->getProductsForSelect();
        $categories = $this->couponService->getCategoriesForSelect();

        return view('admin.coupon.edit', compact('coupon', 'products', 'categories'));
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon)
    {
        $this->couponService->update($coupon->getKey(), $request->validated());

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        $this->couponService->delete($coupon->getKey());

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Coupon deleted successfully.');
    }

    public function toggleStatus(Coupon $coupon)
    {
        $this->couponService->toggleStatus($coupon);

        return back()->with('success', 'Coupon status updated.');
    }
}
