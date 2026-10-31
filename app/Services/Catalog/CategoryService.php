<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(
        protected Category $category,
        protected NotificationService $notificationService,
        protected AuditService $auditService
    ) {}

    /**
     * The admin listing, which has to include deactivated categories.
     *
     * `getAll()` applies the `active` scope for storefront callers. Reusing it
     * here meant that switching a category off with the status toggle made it
     * vanish from the admin screen with no way to find it again or switch it
     * back on.
     */
    public function getAllForAdmin(): Collection
    {
        return Category::with(['parent', 'children'])
            ->withCount(['products', 'children'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getAll(): Collection
    {
        return Category::with(['parent', 'children'])
            ->withCount('products')
            ->active()
            ->orderBy('sort_order')
            ->get();
    }

    public function getTree(): Collection
    {
        // `onHome` constrains the root level only, and deliberately only this
        // method: it is the single caller behind the storefront home page, so
        // navigation, the mobile menu and the category page are untouched. The
        // home grid renders these roots and nothing else, so the eager-loaded
        // children stay unfiltered — if the grid ever starts rendering
        // subcategories, the scope has to move into the nested closures too.
        return Category::with(['children' => function ($q) {
            $q->with(['children' => function ($q2) {
                $q2->with('children');
            }]);
        }])
            ->active()
            ->root()
            ->onHome()
            ->orderBy('sort_order')
            ->get();
    }

    public function getBySlug(string $slug): Category
    {
        return Category::with(['children', 'products'])
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();
    }

    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $category = Category::create($this->withStoredImage($data));

            CatalogCache::forgetCategories();

            $this->notificationService->sendToAllAdmins(
                title: 'Category Created',
                message: "Category '{$category->name}' has been created.",
                data: ['category_id' => $category->getKey()]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'created',
                entity: Category::class,
                entityId: $category->getKey(),
                newValues: $category->toArray()
            );

            return $category;
        });
    }

    public function update(string $id, array $data): Category
    {
        return DB::transaction(function () use ($id, $data) {
            $category = Category::findOrFail($id);
            $oldValues = $category->toArray();
            $category->update($this->withStoredImage($data));

            CatalogCache::forgetCategories();
            CatalogCache::forgetCategoryBrands($category->getKey());

            $this->notificationService->sendToAllAdmins(
                title: 'Category Updated',
                message: "Category '{$category->name}' has been updated.",
                data: ['category_id' => $category->getKey()]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'updated',
                entity: Category::class,
                entityId: $category->getKey(),
                oldValues: $oldValues,
                newValues: $category->fresh()->toArray()
            );

            return $category->load('children');
        });
    }

    /**
     * `image` is validated as a file, so validated() hands over an
     * UploadedFile while categories.image is a char(255) column: binding the
     * object straight into Category::create()/update() is a PDO error on every
     * category save with an image. The upload is persisted and the stored
     * path replaces the object; a value that is already a path is left alone.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withStoredImage(array $data): array
    {
        $image = $data['image'] ?? null;

        if ($image instanceof UploadedFile) {
            $data['image'] = $image->store('categories', 'public');
        }

        return $data;
    }

    /**
     * A category holding products cannot be deleted.
     *
     * This used to throw a bare \Exception, which Laravel rendered as an HTTP
     * 500 error page: the operator saw a stack-trace screen instead of a
     * message naming the category they had to empty first.
     */
    public function delete(string $id): bool
    {
        $category = Category::findOrFail($id);

        if ($category->products()->count() > 0) {
            throw ValidationException::withMessages([
                'category' => 'Move the '.$category->products()->count().' product(s) in "'.$category->name.'" to another category before deleting it.',
            ]);
        }

        $categoryData = $category->toArray();
        $deleted = $category->delete();

        CatalogCache::forgetCategories();
        CatalogCache::forgetCategoryBrands($id);

        if ($deleted) {
            $this->notificationService->sendToAllAdmins(
                title: 'Category Deleted',
                message: "Category '{$categoryData['name']}' has been deleted.",
                data: ['category_id' => $id]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'deleted',
                entity: Category::class,
                entityId: $id,
                oldValues: $categoryData
            );
        }

        return $deleted;
    }

    public function toggleStatus(Category $category): Category
    {
        $oldValues = $category->toArray();
        $category->update(['is_active' => ! $category->is_active]);

        CatalogCache::forgetCategories();
        CatalogCache::forgetCategoryBrands($category->getKey());

        $newValues = $category->fresh()->toArray();

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'updated',
            entity: Category::class,
            entityId: $category->getKey(),
            oldValues: $oldValues,
            newValues: $newValues
        );

        return $category->fresh();
    }

    public function getAllCategories(): Collection
    {
        return $this->getAll();
    }

    public function getParentCategories(?string $excludeId = null)
    {
        $query = Category::active()->whereNull('parent_id')->orderBy('name');

        if ($excludeId) {
            $query->where('_id', '!=', $excludeId);
        }

        return $query->get();
    }

    public function createCategory(array $data): Category
    {
        return $this->create($data);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        return $this->update($category->getKey(), $data);
    }

    public function deleteCategory(Category $category): bool
    {
        return $this->delete($category->getKey());
    }
}
