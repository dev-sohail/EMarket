<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCampaignRequest;
use App\Http\Requests\Admin\UpdateCampaignRequest;
use App\Models\Category;
use App\Models\DiscountCampaign;
use App\Models\Product;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'type' => 'nullable|in:percentage,fixed',
            'sort_by' => 'nullable|in:name,type,value,starts_at,ends_at,created_at',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $filters = array_filter($request->only([
            'search', 'status', 'type', 'sort_by', 'sort_dir',
        ]), fn ($value) => $value !== null && $value !== '');

        $query = DiscountCampaign::query()
            ->withCount(['products', 'categories'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type));

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        $query->orderBy($sortBy, $sortDir);

        $campaigns = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => DiscountCampaign::count(),
            'active' => DiscountCampaign::where('is_active', true)->count(),
            'inactive' => DiscountCampaign::where('is_active', false)->count(),
            'scheduled' => DiscountCampaign::where('starts_at', '>', now())->count(),
            'ended' => DiscountCampaign::where('ends_at', '<', now())->count(),
        ];

        return view('admin.campaigns.index', compact('campaigns', 'filters', 'stats'));
    }

    public function create()
    {
        $products = Product::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        return view('admin.campaigns.create', compact('products', 'categories'));
    }

    public function store(StoreCampaignRequest $request)
    {
        $campaign = DiscountCampaign::create($request->validated());

        if ($request->has('category_ids')) {
            $campaign->categories()->sync($request->input('category_ids', []));
        }

        if ($request->has('product_ids')) {
            $campaign->products()->sync($this->productSyncData($request->input('product_ids'), $campaign));
        }

        return redirect()->route('admin.campaigns.index')
            ->with('success', 'Campaign created successfully.');
    }

    public function edit(DiscountCampaign $campaign)
    {
        $campaign->load('products', 'categories');
        $products = Product::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        return view('admin.campaigns.edit', compact('campaign', 'products', 'categories'));
    }

    public function update(UpdateCampaignRequest $request, DiscountCampaign $campaign)
    {
        $campaign->update($request->validated());

        if ($request->has('category_ids')) {
            $campaign->categories()->sync($request->input('category_ids', []));
        }

        if ($request->has('product_ids')) {
            $campaign->products()->sync($this->productSyncData($request->input('product_ids'), $campaign, $request->input('product_prices', [])));
        }

        return redirect()->route('admin.campaigns.index')
            ->with('success', 'Campaign updated successfully.');
    }

    public function destroy(DiscountCampaign $campaign)
    {
        $campaign->products()->detach();
        $campaign->categories()->detach();
        $campaign->delete();

        return redirect()->route('admin.campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }

    public function toggleStatus(DiscountCampaign $campaign)
    {
        $campaign->update(['is_active' => ! $campaign->is_active]);

        return back()->with('success', 'Campaign status updated.');
    }

    protected function productSyncData(array $productIds, DiscountCampaign $campaign, array $productPrices = []): array
    {
        return collect($productIds)->mapWithKeys(function ($id) use ($campaign, $productPrices) {
            $price = $productPrices[$id] ?? null;

            if ($price === null || $price === '') {
                $product = Product::find($id);
                $basePrice = $product?->base_price ?? 0;

                if ($campaign->type === 'percentage') {
                    $price = max(0, round($basePrice * (1 - ($campaign->value ?? 0) / 100), 2));
                } else {
                    $price = max(0, round($basePrice - ($campaign->value ?? 0), 2));
                }
            }

            return [$id => ['campaign_price' => $price ?? 0]];
        })->all();
    }
}
