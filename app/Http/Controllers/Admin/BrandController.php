<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\Catalog\BrandService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    protected BrandService $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    public function index(Request $request)
    {
        // Validated rather than trusted: these values go straight into where
        // clauses, so an unknown status would otherwise silently return the
        // unfiltered list instead of reporting the typo.
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        $filters = $request->only(['search', 'status']);

        // The stat cards describe the whole brand list, so they are counted
        // before the filters below narrow the paginated query.
        $stats = [
            'total' => Brand::count(),
            'active' => Brand::where('is_active', true)->count(),
            'inactive' => Brand::where('is_active', false)->count(),
            'unused' => Brand::doesntHave('products')->count(),
        ];

        $brands = Brand::withCount('products')->latest();

        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';

            $brands->where(fn ($query) => $query
                ->where('name', 'like', $needle)
                ->orWhere('slug', 'like', $needle)
                ->orWhere('website', 'like', $needle)
            );
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $brands->where('is_active', $filters['status'] === 'active');
        }

        return view('admin.brands.index', [
            'brands' => $brands->paginate(15)->withQueryString(),
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return view('admin.brands.create');
    }

    public function store(StoreBrandRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $this->brandService->create($data);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand created successfully.');
    }

    public function edit(Brand $brand)
    {
        return view('admin.brands.edit', compact('brand'));
    }

    public function update(UpdateBrandRequest $request, Brand $brand)
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($brand->logo) {
                \Storage::disk('public')->delete($brand->logo);
            }
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $this->brandService->update($brand->getKey(), $data);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand)
    {
        try {
            $this->brandService->delete($brand->getKey());

            return redirect()->route('admin.brands.index')
                ->with('success', 'Brand deleted successfully.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->route('admin.brands.index')
                ->with('error', 'Failed to delete brand. Please try again.');
        }
    }

    public function toggleStatus(Brand $brand)
    {
        $this->brandService->toggleStatus($brand);

        return back()->with('success', 'Brand status updated.');
    }
}
