<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(Request $request)
    {
        // Validated rather than trusted: the values below go straight into
        // where clauses, and an unknown status would otherwise silently return
        // the unfiltered list instead of reporting the typo.
        $request->validate([
            'search' => 'nullable|string|max:255',
            'parent' => 'nullable|string|max:36',
            'status' => 'nullable|in:active,inactive',
        ]);

        $filters = $request->only(['search', 'parent', 'status']);

        $all = $this->categoryService->getAllForAdmin();

        // The filter dropdown and the stat cards describe the whole catalogue,
        // so both come from the unfiltered set and are computed before the
        // narrowing below replaces `$all`.
        $parents = $all->whereNull('parent_id')->sortBy('name');

        $stats = [
            'total' => $all->count(),
            'active' => $all->where('is_active', true)->count(),
            'inactive' => $all->where('is_active', false)->count(),
            'top_level' => $parents->count(),
        ];

        $categories = $all;

        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $needle = mb_strtolower($filters['search']);

            $categories = $categories->filter(fn (Category $category) => str_contains(
                mb_strtolower($category->name).' '.mb_strtolower($category->slug),
                $needle
            ));
        }

        if (($filters['parent'] ?? null) !== null && $filters['parent'] !== '') {
            $categories = $categories->filter(
                fn (Category $category) => $category->parent_id === $filters['parent']
            );
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $categories = $categories->filter(
                fn (Category $category) => $filters['status'] === 'active'
                    ? $category->is_active
                    : ! $category->is_active
            );
        }

        return view('admin.category.index', compact('categories', 'filters', 'parents', 'stats', 'all'));
    }

    public function create()
    {
        $parentCategories = $this->categoryService->getParentCategories();

        return view('admin.category.create', compact('parentCategories'));
    }

    public function store(StoreCategoryRequest $request)
    {
        $this->categoryService->createCategory($request->validated());

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(Category $category)
    {
        $parentCategories = $this->categoryService->getParentCategories($category->getKey());

        return view('admin.category.edit', compact('category', 'parentCategories'));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->categoryService->updateCategory($category, $request->validated());

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        $this->categoryService->deleteCategory($category);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    public function toggleStatus(Category $category)
    {
        $this->categoryService->toggleStatus($category);

        return back()->with('success', 'Category status updated.');
    }
}
