<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ajax\AjaxReviewRequest;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {}

    public function store(AjaxReviewRequest $request): JsonResponse
    {
        try {
            $customer = auth()->user()?->customer;

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please login to submit a review.',
                ], 401);
            }

            $data = $request->validated();
            $data['customer_id'] = $customer->getKey();
            $data['images'] = $this->storeImages($request->file('images') ?? []);

            $review = $this->reviewService->create($data);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully. It will be visible after approval.',
                'data' => [
                    'review' => $review->load('images'),
                ],
            ]);
        } catch (\Exception $e) {
            // Never echo the exception message back to the client: a
            // QueryException carries table names, column names and driver text.
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'We could not submit your review right now. Please try again later.',
            ], 500);
        }
    }

    /**
     * ReviewService persists whatever string it is handed into
     * review_images.path, so the uploads have to reach disk first. Requiring
     * `images.*` to be a real file also stops a caller submitting
     * `images[0]=../../../../etc/passwd`, which used to be written straight
     * into that column.
     *
     * @param  array<int, mixed>  $images
     * @return array<int, string>
     */
    protected function storeImages(array $images): array
    {
        $paths = [];

        foreach ($images as $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            $path = $image->store('reviews', 'public');

            if (is_string($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
