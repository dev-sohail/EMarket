<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreReviewRequest;
use App\Http\Requests\Store\UpdateReviewRequest;
use App\Models\Product;
use App\Models\Review;
use App\Services\Review\ReviewService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {}

    public function index()
    {
        $customer = auth()->user()->customer;
        $reviews = Review::with(['product.images', 'images'])
            ->where('customer_id', $customer->getKey())
            ->latest()
            ->paginate(15);

        return view('customer.review.index', compact('reviews'));
    }

    public function create(Product $product)
    {
        $customer = auth()->user()->customer;

        $existingReview = Review::where('customer_id', $customer->getKey())
            ->where('product_id', $product->getKey())
            ->first();

        if ($existingReview) {
            return redirect()->route('customer.reviews.edit', $existingReview)
                ->with('info', 'You have already reviewed this product.');
        }

        return view('customer.review.create', compact('product'));
    }

    public function store(StoreReviewRequest $request)
    {
        try {
            $customer = auth()->user()->customer;

            $data = $request->validated();
            $data['customer_id'] = $customer->getKey();

            $review = $this->reviewService->create($data);

            return redirect()->route('customer.reviews')
                ->with('success', 'Review submitted successfully. It will be visible after approval.');
        } catch (QueryException $e) {
            Log::error('Review creation failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->withInput()
                ->with('error', 'Failed to submit review due to a system error. Please try again.');
        }
    }

    public function edit(Review $review)
    {
        $customer = auth()->user()->customer;
        abort_if($review->customer_id !== $customer->getKey(), 403);

        $review->load('product', 'images');

        return view('customer.review.edit', compact('review'));
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        $customer = auth()->user()->customer;
        abort_if($review->customer_id !== $customer->getKey(), 403);

        try {
            $review->update($request->validated());

            return redirect()->route('customer.reviews')
                ->with('success', 'Review updated successfully.');
        } catch (QueryException $e) {
            Log::error('Review update failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->withInput()
                ->with('error', 'Failed to update review due to a system error. Please try again.');
        }
    }

    public function destroy(Review $review)
    {
        $customer = auth()->user()->customer;
        abort_if($review->customer_id !== $customer->getKey(), 403);

        try {
            $this->reviewService->delete($review->getKey());

            return redirect()->route('customer.reviews')
                ->with('success', 'Review deleted successfully.');
        } catch (QueryException $e) {
            Log::error('Review deletion failed: '.$e->getMessage(), ['exception' => $e]);

            return back()->with('error', 'Failed to delete review due to a system error. Please try again.');
        }
    }
}
