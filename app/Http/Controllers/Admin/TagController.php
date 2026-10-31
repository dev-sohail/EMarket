<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTagRequest;
use App\Http\Requests\Admin\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin CRUD for product tags.
 *
 * Validation and slug derivation live in the form requests, because the slug is
 * derived from the name and its uniqueness is the constraint the database
 * actually enforces — a rule that only the request knows about.
 */
class TagController extends Controller
{
    public function index(Request $request): View
    {
        $query = Tag::withCount('products');

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->get('sort', 'name_asc');
        match ($sort) {
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'products_desc' => $query->orderBy('products_count', 'desc'),
            'products_asc' => $query->orderBy('products_count', 'asc'),
            'newest' => $query->latest(),
            'oldest' => $query->oldest(),
            default => $query->orderBy('name', 'asc'),
        };

        $tags = $query->paginate(25)->withQueryString();

        return view('admin.tags.index', compact('tags'));
    }

    public function create(): View
    {
        return view('admin.tags.create');
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        Tag::create($request->tagAttributes());

        return redirect()
            ->route('admin.tags.index')
            ->with('success', 'Tag created successfully.');
    }

    public function edit(Tag $tag): View
    {
        return view('admin.tags.edit', compact('tag'));
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->tagAttributes());

        return redirect()
            ->route('admin.tags.index')
            ->with('success', 'Tag updated successfully.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        // The pivot cleanup is a `deleting` hook on Tag, so it also runs for
        // deletes that do not come through this controller.
        $tag->delete();

        return redirect()
            ->route('admin.tags.index')
            ->with('success', 'Tag deleted successfully.');
    }
}
