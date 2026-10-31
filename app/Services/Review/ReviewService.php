<?php

namespace App\Services\Review;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function __construct(
        protected Review $review
    ) {}

    public function create(array $data): Review
    {
        return DB::transaction(function () use ($data) {
            $existing = $this->review->where('product_id', $data['product_id'])
                ->where('customer_id', $data['customer_id'])
                ->first();

            if ($existing) {
                throw new \Exception('You have already reviewed this product.');
            }

            $isVerifiedPurchase = $this->isVerifiedPurchase(
                $data['customer_id'],
                $data['product_id']
            );

            $review = $this->review->create([
                'product_id' => $data['product_id'],
                'customer_id' => $data['customer_id'],
                'order_id' => $data['order_id'] ?? null,
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'comment' => $data['comment'] ?? null,
                'status' => ReviewStatus::Pending->value,
                'is_verified_purchase' => $isVerifiedPurchase,
            ]);

            if (! empty($data['images'])) {
                foreach ($data['images'] as $image) {
                    $review->images()->create(['path' => $image]);
                }
            }

            return $review->load('images');
        });
    }

    protected function isVerifiedPurchase(string $customerId, string $productId): bool
    {
        return OrderItem::where('product_id', $productId)
            ->whereHas('order', fn ($query) => $query
                ->where('customer_id', $customerId)
                ->whereIn('status', [
                    OrderStatus::Delivered->value,
                    OrderStatus::Completed->value,
                ])
                ->where('payment_status', PaymentStatus::Paid->value)
            )
            ->exists();
    }

    public function approve(string $id): Review
    {
        $review = $this->review->findOrFail($id);
        $review->update([
            'status' => ReviewStatus::Approved->value,
        ]);

        return $review->fresh();
    }

    public function reject(string $id): Review
    {
        $review = $this->review->findOrFail($id);
        $review->update([
            'status' => ReviewStatus::Rejected->value,
        ]);

        return $review->fresh();
    }

    public function delete(string $id): bool
    {
        $review = $this->review->findOrFail($id);

        return DB::transaction(function () use ($review) {
            $review->images()->delete();

            return $review->delete();
        });
    }

    public function getProductReviews(string $productId): Collection
    {
        return $this->review->with(['customer', 'images'])
            ->where('product_id', $productId)
            ->where('status', ReviewStatus::Approved->value)
            ->latest()
            ->get();
    }

    public function getPending(): Collection
    {
        return $this->review->with(['customer', 'product', 'images'])
            ->where('status', ReviewStatus::Pending->value)
            ->latest()
            ->get();
    }
}
