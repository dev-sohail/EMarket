<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFlashSaleRequest;
use App\Http\Requests\Admin\UpdateFlashSaleRequest;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FlashSaleController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'state' => 'nullable|in:live,scheduled,ended',
            'sort_by' => 'nullable|in:name,starts_at,ends_at,sale_price,created_at',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        $filters = array_filter($request->only([
            'search', 'status', 'state', 'sort_by', 'sort_order',
        ]), fn ($value) => $value !== null && $value !== '');

        $now = Carbon::now();

        $query = FlashSale::query()
            ->with('products')
            ->withCount(['products', 'categories'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false));

        if (($state = $filters['state'] ?? null) === 'live') {
            $query->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now);
        } elseif ($state === 'scheduled') {
            $query->where('starts_at', '>', $now);
        } elseif ($state === 'ended') {
            $query->where('ends_at', '<', $now);
        }

        $sortBy = $filters['sort_by'] ?? 'starts_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $query->orderBy($sortBy === 'name' ? 'name' : $sortBy, $sortOrder);

        $flashSales = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => FlashSale::count(),
            'live' => FlashSale::where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>=', $now)->count(),
            'scheduled' => FlashSale::where('starts_at', '>', $now)->count(),
            'ended' => FlashSale::where('ends_at', '<', $now)->count(),
        ];

        return view('admin.flash-sales.index', compact('flashSales', 'filters', 'stats'));
    }

    public function create()
    {
        $products = Product::orderBy('name')->get();
        $categories = Category::active()->orderBy('name')->get();

        return view('admin.flash-sales.create', compact('products', 'categories'));
    }

    public function store(StoreFlashSaleRequest $request)
    {
        $data = $request->validated();
        $data['starts_at'] = $this->toStoreUtc($data['starts_at'] ?? null);
        $data['ends_at'] = $this->toStoreUtc($data['ends_at'] ?? null);
        unset($data['product_ids'], $data['category_ids'], $data['product_prices']);

        $flashSale = FlashSale::create($data);

        if ($request->has('category_ids')) {
            $flashSale->categories()->sync($request->input('category_ids', []));
        }

        $productIds = $this->resolveProductIds(
            $request->input('product_ids', []),
            $request->input('category_ids', [])
        );

        if (! empty($productIds)) {
            $flashSale->products()->attach($this->productSyncData($productIds, $request->input('sale_price'), $request->input('product_prices', [])));
        }

        return redirect()->route('admin.flash-sales.index')
            ->with('success', 'Flash sale created successfully.');
    }

    public function edit(FlashSale $flashSale)
    {
        $flashSale->load('products', 'categories');
        $products = Product::orderBy('name')->get();
        $categories = Category::active()->orderBy('name')->get();

        return view('admin.flash-sales.edit', compact('flashSale', 'products', 'categories'));
    }

    public function update(UpdateFlashSaleRequest $request, FlashSale $flashSale)
    {
        $data = $request->validated();
        $data['starts_at'] = array_key_exists('starts_at', $data)
            ? $this->toStoreUtc($data['starts_at'])
            : $flashSale->starts_at;
        $data['ends_at'] = array_key_exists('ends_at', $data)
            ? $this->toStoreUtc($data['ends_at'])
            : $flashSale->ends_at;
        unset($data['product_ids'], $data['category_ids'], $data['product_prices']);

        $flashSale->update($data);

        if ($request->has('category_ids')) {
            $flashSale->categories()->sync($request->input('category_ids', []));
        }

        $productIds = $this->resolveProductIds(
            $request->input('product_ids', []),
            $request->input('category_ids', [])
        );

        $flashSale->products()->sync($this->productSyncData($productIds, $request->input('sale_price'), $request->input('product_prices', [])));

        return redirect()->route('admin.flash-sales.index')
            ->with('success', 'Flash sale updated successfully.');
    }

    public function destroy(FlashSale $flashSale)
    {
        $flashSale->products()->detach();
        $flashSale->categories()->detach();
        $flashSale->delete();

        return redirect()->route('admin.flash-sales.index')
            ->with('success', 'Flash sale deleted successfully.');
    }

    public function toggleStatus(FlashSale $flashSale)
    {
        $flashSale->update(['is_active' => ! $flashSale->is_active]);

        return back()->with('success', 'Flash sale status updated.');
    }

    /**
     * datetime-local inputs carry no timezone, so we interpret them as
     * store-local time (per the configured timezone setting) and store as-is.
     */
    protected function toStoreUtc($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $timezone = Cache::remember('app_timezone', 3600, fn () => setting('timezone', config('app.timezone')));

        return Carbon::parse($value, $timezone)->format('Y-m-d H:i:s');
    }

    protected function resolveProductIds(array $productIds, array $categoryIds): array
    {
        $ids = collect($productIds)->filter()->map(fn ($id) => (string) $id);

        if (! empty($categoryIds)) {
            $categoryProductIds = Product::whereHas('categories', fn ($q) => $q->whereIn('_id', $categoryIds))
                ->pluck('_id');

            $ids = $ids->concat($categoryProductIds);
        }

        return $ids->unique()->values()->all();
    }

    protected function productSyncData(array $productIds, $salePrice, array $productPrices = []): array
    {
        $defaultPrice = $salePrice ?? 0;

        return collect($productIds)->mapWithKeys(function ($id) use ($defaultPrice, $productPrices) {
            $price = $productPrices[$id] ?? $defaultPrice;

            if ($price === null || $price === '') {
                $price = $defaultPrice;
            }

            return [$id => ['flash_price' => $price ?? 0]];
        })->all();
    }
}
