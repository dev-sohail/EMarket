<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBannerRequest;
use App\Http\Requests\Admin\UpdateBannerRequest;
use App\Models\Banner;
use App\Services\Shared\ImageCompression;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function __construct(protected ImageCompression $compression) {}

    private function normalizeDates(array $validated): array
    {
        foreach (['starts_at', 'ends_at'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = Carbon::createFromFormat('Y-m-d\TH:i', $validated[$field])->toDateTimeString();
            }
        }

        return $validated;
    }

    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'position' => 'nullable|in:homepage,promotional,footer,sale',
            'status' => 'nullable|in:active,inactive',
            'state' => 'nullable|in:live,scheduled,expired',
            'sort_by' => 'nullable|in:sort_order,title,position,starts_at,ends_at,created_at',
            'sort_order' => 'nullable|in:asc,desc',
        ]);

        $filters = array_filter($request->only([
            'search', 'position', 'status', 'state', 'sort_by', 'sort_order',
        ]), fn ($value) => $value !== null && $value !== '');

        $now = Carbon::now();

        $query = Banner::query()
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('subtitle', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('cta_text', 'like', '%'.addcslashes($search, '%_\\').'%'),
            ))
            ->when($filters['position'] ?? null, fn ($q, $position) => $q->where('position', $position))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false));

        $state = $filters['state'] ?? null;

        if ($state === 'live') {
            $query->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
        } elseif ($state === 'scheduled') {
            $query->whereNotNull('starts_at')->where('starts_at', '>', $now);
        } elseif ($state === 'expired') {
            $query->whereNotNull('ends_at')->where('ends_at', '<', $now);
        }

        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $banners = $query->orderBy($sortBy, $sortOrder)
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => Banner::count(),
            'live' => Banner::where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->count(),
            'scheduled' => Banner::whereNotNull('starts_at')->where('starts_at', '>', $now)->count(),
            'expired' => Banner::whereNotNull('ends_at')->where('ends_at', '<', $now)->count(),
        ];

        return view('admin.banners.index', compact('banners', 'filters', 'stats'));
    }

    public function create()
    {
        return view('admin.banners.create');
    }

    public function store(StoreBannerRequest $request)
    {
        $validated = $request->validated();

        if ($request->input('position') === 'sale') {
            unset($validated['image']);
        } elseif ($request->hasFile('image')) {
            $validated['image'] = $this->compression->compress($request->file('image'), 1400, 82)->store('banners', 'public');
        }

        $validated = $this->normalizeDates($validated);

        Banner::create($validated);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner created successfully.');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.edit', compact('banner'));
    }

    public function update(UpdateBannerRequest $request, Banner $banner)
    {
        $validated = $request->validated();

        if ($request->input('position') === 'sale') {
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $validated['image'] = null;
        } elseif ($request->hasFile('image')) {
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }
            $validated['image'] = $this->compression->compress($request->file('image'), 1400, 82)->store('banners', 'public');
        }

        $validated = $this->normalizeDates($validated);

        $banner->update($validated);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner updated successfully.');
    }

    public function destroy(Banner $banner)
    {
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner deleted successfully.');
    }

    public function toggleStatus(Banner $banner)
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('success', 'Banner status updated.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'banners' => 'required|array',
            'banners.*' => 'exists:banners,_id',
        ]);

        foreach ($request->input('banners') as $index => $id) {
            Banner::where('_id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
