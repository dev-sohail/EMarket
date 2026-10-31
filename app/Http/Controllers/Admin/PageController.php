<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePageRequest;
use App\Http\Requests\Admin\UpdatePageRequest;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request)
    {
        // Validated rather than trusted: the values below go straight into
        // where clauses and an order-by column, so a typo would otherwise
        // either return the unfiltered list or blow up on the sort.
        $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|in:published,draft',
            'sort' => 'nullable|in:newest,oldest,title_asc,title_desc',
        ]);

        $filters = $request->only(['search', 'status', 'sort']);

        // The stat cards describe the whole library, so they are counted
        // before the narrowing below replaces the query.
        $stats = [
            'total' => Page::query()->count(),
            'published' => Page::query()->where('status', 'published')->count(),
            'draft' => Page::query()->where('status', 'draft')->count(),
        ];

        $query = Page::query();

        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';

            $query->where(function ($builder) use ($needle) {
                $builder->where('title', 'like', $needle)
                    ->orWhere('slug', 'like', $needle);
            });
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'title_asc' => $query->orderBy('title'),
            'title_desc' => $query->orderByDesc('title'),
            default => $query->latest(),
        };

        $pages = $query->paginate(15)->withQueryString();

        return view('admin.pages.index', compact('pages', 'filters', 'stats'));
    }

    public function create()
    {
        return view('admin.pages.create');
    }

    public function store(StorePageRequest $request)
    {
        Page::create($request->validated());

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page created successfully.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.edit', compact('page'));
    }

    public function update(UpdatePageRequest $request, Page $page)
    {
        $page->update($request->validated());

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Page deleted successfully.');
    }

    public function toggleStatus(Page $page)
    {
        $page->update(['status' => $page->status === 'published' ? 'draft' : 'published']);

        return back()->with('success', 'Page status updated.');
    }
}
