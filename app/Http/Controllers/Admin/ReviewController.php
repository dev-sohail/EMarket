<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:'.implode(',', array_column(ReviewStatus::cases(), 'value')),
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $status = $request->input('status');
        $search = $request->input('search');
        $perPage = (int) ($request->input('per_page', 15));

        $query = Review::with(['customer', 'product.images', 'images']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $reviews = $query->latest('created_at')->paginate($perPage)->appends($request->except('page'));

        $stats = Review::select([
            DB::raw("SUM(CASE WHEN status = '".ReviewStatus::Pending->value."' THEN 1 ELSE 0 END) as pending"),
            DB::raw("SUM(CASE WHEN status = '".ReviewStatus::Approved->value."' THEN 1 ELSE 0 END) as approved"),
            DB::raw("SUM(CASE WHEN status = '".ReviewStatus::Rejected->value."' THEN 1 ELSE 0 END) as rejected"),
            DB::raw('COUNT(*) as total'),
            DB::raw('ROUND(AVG(rating), 1) as average'),
        ])->first()->toArray();

        return view('admin.reviews.index', compact('reviews', 'status', 'search', 'stats'));
    }

    public function approve(Review $review)
    {
        $review->update(['status' => ReviewStatus::Approved->value]);

        return back()->with('success', 'Review approved successfully.');
    }

    public function reject(Review $review)
    {
        $review->update(['status' => ReviewStatus::Rejected->value]);

        return back()->with('success', 'Review rejected successfully.');
    }

    public function destroy(Review $review)
    {
        $review->images()->delete();
        $review->delete();

        return back()->with('success', 'Review deleted successfully.');
    }

    public function toggleFront()
    {
        $current = setting('reviews_enabled', true);

        Setting::put('reviews_enabled', $current ? '0' : '1', 'review');
        setting_forget();

        return back()->with('success', $current ? 'Reviews hidden on frontend.' : 'Reviews shown on frontend.');
    }
}
