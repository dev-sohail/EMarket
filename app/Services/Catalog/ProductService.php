<?php

namespace App\Services\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\Audit\AuditService;
use App\Services\Inventory\InventoryService;
use App\Services\Notification\NotificationService;
use App\Services\Shared\ImageCompression;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    protected ImageCompression $compression;

    public function __construct(
        protected Product $product,
        ImageCompression $compression,
        protected NotificationService $notificationService,
        protected AuditService $auditService,
        protected InventoryService $inventoryService
    ) {
        $this->compression = $compression;
    }

    private const ALLOWED_SEARCH_SORT_COLUMNS = [
        'name', 'sku', 'base_price', 'created_at', 'updated_at',
    ];

    private const ALLOWED_ADMIN_SORT_COLUMNS = [
        'name', 'sku', 'base_price', 'stock_status', 'is_active', 'is_featured', 'created_at', 'updated_at',
    ];

    /**
     * Narrow the catalogue to products carrying selected attribute values.
     *
     * Each key is an attribute key and each value a list of that attribute's
     * value ids. `whereHas` per attribute means different attributes narrow
     * together (AND), while `whereIn` inside one means any of that attribute's
     * selected values is enough (OR) — "Red or Blue" should widen the result,
     * not require the product to be both.
     *
     * An empty group is skipped rather than matching nothing, so a stray empty
     * query parameter cannot blank the catalogue.
     *
     * @param  array<string, array<int, string>>  $attributeValueIds
     */
    protected function applyAttributeFilters(Builder $query, array $attributeValueIds): void
    {
        foreach ($attributeValueIds as $valueIds) {
            $valueIds = array_values(array_filter((array) $valueIds));

            if ($valueIds === []) {
                continue;
            }

            $query->whereHas('attributeValues', function ($q) use ($valueIds) {
                $q->whereIn('attribute_values._id', $valueIds);
            });
        }
    }

    public function search(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['categories', 'brands', 'images', 'variants', 'flashSales', 'campaigns',
                'reviews' => fn ($q) => $q->where('status', 'approved')])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
            ->published();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $categoryIds = is_array($filters['category_id']) ? $filters['category_id'] : [$filters['category_id']];
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories._id', $categoryIds));
        }

        if (! empty($filters['brand_id'])) {
            $brandIds = is_array($filters['brand_id']) ? $filters['brand_id'] : [$filters['brand_id']];
            $query->whereHas('brands', fn ($q) => $q->whereIn('brands._id', $brandIds));
        }

        $this->applyAttributeFilters($query, $filters['attribute_value_ids'] ?? []);

        if (! empty($filters['min_price'])) {
            $query->where('base_price', '>=', $filters['min_price']);
        }

        if (! empty($filters['max_price'])) {
            $query->where('base_price', '<=', $filters['max_price']);
        }

        if (! empty($filters['is_featured'])) {
            $query->featured();
        }

        if (! empty($filters['is_new'])) {
            $query->new();
        }

        if (! empty($filters['sale'])) {
            $query->onSale();
        }

        $sortBy = in_array($filters['sort_by'] ?? '', self::ALLOWED_SEARCH_SORT_COLUMNS, true)
            ? $filters['sort_by'] : 'created_at';
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc'], true)
            ? strtolower($filters['sort_dir']) : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getById(string $id): Product
    {
        return Product::with([
            'categories', 'brands', 'tags', 'images',
            'variants.images', 'attributeValues.attribute',
        ])->findOrFail($id);
    }

    public function getBySlug(string $slug): Product
    {
        return Product::with([
            'categories', 'brands', 'tags', 'images',
            'variants.images', 'attributeValues.attribute',
        ])->where('slug', $slug)->published()->firstOrFail();
    }

    public function getFeatured(int $limit = 8)
    {
        return Product::with(['images', 'variants', 'categories',
            'reviews' => fn ($q) => $q->where('status', 'approved')])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
            ->published()
            ->featured()
            ->limit($limit)
            ->get();
    }

    public function getNewArrivals(int $limit = 8)
    {
        return Product::with(['images', 'variants', 'categories',
            'reviews' => fn ($q) => $q->where('status', 'approved')])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
            ->published()
            ->new()
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getBestSellers(int $limit = 8)
    {
        $bestSellers = Product::with(['images', 'variants', 'categories',
            'reviews' => fn ($q) => $q->where('status', 'approved')])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
            ->published()
            ->withSum(['orderItems as total_sold' => function ($q) {
                $q->join('orders', 'order_items.order_id', '=', 'orders._id')
                    ->where('orders.status', '!=', 'cancelled');
            }], 'order_items.quantity')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();

        // Only keep products that actually have sales
        $productsWithSales = $bestSellers->filter(function ($product) {
            return ! is_null($product->total_sold) && $product->total_sold > 0;
        });

        // If we have no products with actual sales, return empty collection
        // This will hide the Best Sellers section entirely on the homepage
        if ($productsWithSales->isEmpty()) {
            return collect();
        }

        return $productsWithSales->take($limit);
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create($data);
            $product->cost_price = $data['cost_price'] ?? null;
            $product->save();

            if ($this->hasCollection($data, 'category_ids')) {
                $product->categories()->sync($data['category_ids']);
                CatalogCache::forgetCategoryBrands($data['category_ids']);
            }

            if ($this->hasCollection($data, 'brand_ids')) {
                $product->brands()->sync($data['brand_ids']);
                CatalogCache::forgetBrands();
            }

            if ($this->hasCollection($data, 'tag_ids')) {
                $product->tags()->sync($data['tag_ids']);
            }

            if ($this->hasCollection($data, 'attributes')) {
                $this->syncAttributes($product, $data['attributes']);
            }

            if ($this->hasCollection($data, 'variants')) {
                $this->syncVariants($product, $data['variants']);
            } elseif (! $product->variants()->exists()) {
                // Only create base product inventory if product has no variants
                $this->inventoryService->getStock($product->getKey(), null);
            }

            $product->load(['categories', 'brands', 'tags', 'images']);

            $this->notificationService->sendToAllAdmins(
                title: 'Product Created',
                message: "Product '{$product->name}' has been created.",
                data: ['product_id' => $product->getKey()]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'created',
                entity: Product::class,
                entityId: $product->getKey(),
                newValues: $product->toArray()
            );

            return $product;
        });
    }

    /**
     * The collections a product owns are all-or-nothing, and the payload has to
     * say which one it means:
     *
     *  - the key is absent      -> "leave this relation alone";
     *  - the key is an array    -> "replace it with exactly this list", so an
     *                               empty array removes every row;
     *  - anything else          -> not a collection payload, so leave it alone.
     *
     * A key that is merely *present* is therefore not enough: a caller that
     * omits `variants` from a partial update must not have its variant
     * catalogue emptied.
     */
    private function hasCollection(array $data, string $key): bool
    {
        return array_key_exists($key, $data) && is_array($data[$key]);
    }

    public function update(string $id, array $data): Product
    {
        return DB::transaction(function () use ($id, $data) {
            $product = Product::findOrFail($id);
            $oldValues = $product->toArray();
            $product->update($data);
            $product->cost_price = $data['cost_price'] ?? null;
            $product->save();

            if ($this->hasCollection($data, 'category_ids')) {
                $previousCategoryIds = $product->categories()->pluck('categories._id')->all();

                $product->categories()->sync($data['category_ids']);

                CatalogCache::forgetCategoryBrands(
                    array_unique(array_merge($previousCategoryIds, $data['category_ids']))
                );
            }

            if ($this->hasCollection($data, 'brand_ids')) {
                $product->brands()->sync($data['brand_ids']);
                CatalogCache::forgetBrands();
            }

            if ($this->hasCollection($data, 'tag_ids')) {
                $product->tags()->sync($data['tag_ids']);
            }

            if ($this->hasCollection($data, 'attributes')) {
                $this->syncAttributes($product, $data['attributes']);
            }

            if ($this->hasCollection($data, 'variants')) {
                $this->syncVariants($product, $data['variants']);
            }

            // An explicitly emptied variant list leaves the product on base
            // stock; a payload that never mentioned variants must not create
            // that row behind a product that still has its own variants.
            if ($this->hasCollection($data, 'variants')
                && $data['variants'] === []
                && ! $product->variants()->exists()) {
                $this->inventoryService->getStock($product->getKey(), null);
            }

            $product->load(['categories', 'brands', 'tags', 'images']);

            $this->notificationService->sendToAllAdmins(
                title: 'Product Updated',
                message: "Product '{$product->name}' has been updated.",
                data: ['product_id' => $product->getKey()]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'updated',
                entity: Product::class,
                entityId: $product->getKey(),
                oldValues: $oldValues,
                newValues: $product->toArray()
            );

            return $product;
        });
    }

    private function syncAttributes(Product $product, array $attributes): void
    {
        $submittedValueIds = [];
        foreach ($attributes as $attribute) {
            $submittedValueIds = array_merge($submittedValueIds, (array) ($attribute['values'] ?? []));
        }

        $attributeValuesLookup = $this->attributeValuesById($submittedValueIds);

        $pivotData = [];
        foreach ($attributes as $attribute) {
            foreach ((array) ($attribute['values'] ?? []) as $attributeValueId) {
                $attributeValue = $this->lookupAttributeValue($attributeValuesLookup, $attributeValueId);

                // The pivot has no foreign key, so an identifier that does not
                // resolve would be stored as a durable orphan row.
                if ($attributeValue === null) {
                    continue;
                }

                $pivotData[(string) $attributeValue->getKey()] = [
                    'attribute_id' => $attributeValue->attribute_id,
                ];
            }
        }

        $product->attributeValues()->sync($pivotData);
    }

    /**
     * Resolve attribute value identifiers to real rows, keyed by `_id`.
     * Unresolvable or duplicated identifiers are discarded.
     *
     * @param  array<int, mixed>  $ids
     * @return array<string, AttributeValue>
     */
    private function attributeValuesById(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn ($id) => is_scalar($id) ? (string) $id : null, $ids),
            fn (?string $id) => $id !== null && $id !== ''
        )));

        if ($ids === []) {
            return [];
        }

        return AttributeValue::whereIn('_id', $ids)
            ->get(['_id', 'attribute_id'])
            ->keyBy('_id')
            ->all();
    }

    /**
     * @param  array<string, AttributeValue>  $lookup
     */
    private function lookupAttributeValue(array $lookup, mixed $id): ?AttributeValue
    {
        if (! is_scalar($id)) {
            return null;
        }

        return $lookup[(string) $id] ?? null;
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $existingIds = [];
        $allAttributeValueIds = [];

        foreach ($variants as $variantData) {
            $allAttributeValueIds = array_merge($allAttributeValueIds, (array) ($variantData['attribute_values'] ?? []));
        }

        $attributeValuesLookup = $this->attributeValuesById($allAttributeValueIds);

        foreach ($variants as $variantData) {
            $variantData['product_id'] = $product->_id;
            // Cast here: request input arrives as a string while the model casts
            // stock_quantity to int, so comparing the two directly with `!==` was
            // true even for equal values and logged a bogus zero adjustment.
            $stockQuantity = (int) ($variantData['stock'] ?? 0);
            unset($variantData['stock']);

            $attributeValueIds = $variantData['attribute_values'] ?? [];
            unset($variantData['attribute_values']);

            $variantData = $this->withStoredVariantImage($variantData);
            $variantData['combination_key'] = $this->buildCombinationKey($attributeValueIds, $variantData['name'] ?? null, $attributeValuesLookup);

            if (! empty($variantData['id'] ?? null)) {
                $variant = $product->variants()->find($variantData['id']);

                // A submitted id that is not on this product must abort the
                // whole save. Silently dropping the row would leave its id out
                // of $existingIds and turn the trailing delete into a mass
                // deletion of the product's real variants.
                if ($variant === null) {
                    throw ValidationException::withMessages([
                        'variants' => 'One or more selected variants do not belong to this product.',
                    ]);
                }

                $oldStock = $variant->stock_quantity;
                $variant->update($variantData);
                $existingIds[] = $variant->getKey();

                $this->syncVariantAttributes($variant, $attributeValueIds, $attributeValuesLookup);

                if ($stockQuantity !== $oldStock) {
                    $this->inventoryService->adjustStock(
                        $product->getKey(),
                        $variant->getKey(),
                        $stockQuantity - $oldStock,
                        'Stock updated via product edit'
                    );
                }
            } else {
                $existingVariant = $product->variants()
                    ->where('combination_key', $variantData['combination_key'])
                    ->first();

                if ($existingVariant) {
                    throw ValidationException::withMessages([
                        'variants' => 'A variant with the same attribute combination already exists for this product.',
                    ]);
                }

                // Create the variant with zero stock, then let adjustStock seed the
                // inventory row. Creating it with the requested stock here as well
                // would double the quantity: InventoryService::getStock() seeds a
                // fresh inventory row from the variant's own stock_quantity, and
                // adjustStock then increments that by the same amount again.
                $variant = $product->variants()->create(array_merge($variantData, [
                    'stock_quantity' => 0,
                ]));
                $existingIds[] = $variant->getKey();

                $this->syncVariantAttributes($variant, $attributeValueIds, $attributeValuesLookup);

                if ($stockQuantity > 0) {
                    $this->inventoryService->adjustStock(
                        $product->getKey(),
                        $variant->getKey(),
                        $stockQuantity,
                        'Initial stock for new variant'
                    );
                }
            }
        }

        // An empty $existingIds is an explicit "remove every variant" (the
        // caller submitted an empty list), so the predicate is not built from
        // the empty array - that would compile to an always-true "where not in
        // ()" and delete the catalogue on any update that reaches this point.
        $removable = $existingIds === []
            ? $product->variants()
            : $product->variants()->whereNotIn('_id', $existingIds);

        $removable->each(function ($variant) {
            // Check if variant has order items before deleting
            if ($variant->orderItems()->exists()) {
                // Mark as inactive instead of deleting to preserve order history
                $variant->update(['is_active' => false]);
            } else {
                $variant->delete();
            }
        });
    }

    /**
     * `variants.*.image` is validated as a file, so validated() carries an
     * UploadedFile into a char(255) column. Persist it and keep the path; a
     * value that is already a path is left alone.
     *
     * @param  array<string, mixed>  $variantData
     * @return array<string, mixed>
     */
    private function withStoredVariantImage(array $variantData): array
    {
        $image = $variantData['image'] ?? null;

        if ($image instanceof UploadedFile) {
            $variantData['image'] = $image->store('product-variants', 'public');
        }

        return $variantData;
    }

    /**
     * @param  array<int, mixed>  $attributeValueIds
     * @param  array<string, AttributeValue>  $attributeValuesLookup
     */
    private function buildCombinationKey(array $attributeValueIds, ?string $variantName = null, array $attributeValuesLookup = []): string
    {
        if (empty($attributeValueIds)) {
            return 'name:'.($variantName ?? '');
        }

        $values = collect($attributeValueIds)
            ->map(fn ($id) => $this->lookupAttributeValue($attributeValuesLookup, $id))
            ->filter()
            ->sortBy('attribute_id')
            ->sortBy('_id')
            ->values();

        $parts = [];
        foreach ($values as $value) {
            $parts[] = "{$value->attribute_id}:{$value->getKey()}";
        }

        return implode('|', $parts);
    }

    /**
     * @param  array<int, mixed>  $attributeValueIds
     * @param  array<string, AttributeValue>  $attributeValuesLookup
     */
    private function syncVariantAttributes(ProductVariant $variant, array $attributeValueIds, array $attributeValuesLookup = []): void
    {
        $pivotData = [];
        foreach ($attributeValueIds as $valueId) {
            $attributeValue = $this->lookupAttributeValue($attributeValuesLookup, $valueId);

            if ($attributeValue === null) {
                continue;
            }

            $pivotData[(string) $attributeValue->getKey()] = [
                'attribute_id' => $attributeValue->attribute_id,
            ];
        }

        $variant->attributeValues()->sync($pivotData);
    }

    public function delete(string $id): bool
    {
        $product = Product::findOrFail($id);
        $productData = $product->toArray();

        $deleted = $product->delete();

        if ($deleted) {
            $this->notificationService->sendToAllAdmins(
                title: 'Product Deleted',
                message: "Product '{$productData['name']}' has been deleted.",
                data: ['product_id' => $id]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'deleted',
                entity: Product::class,
                entityId: $id,
                oldValues: $productData
            );
        }

        return $deleted;
    }

    public function getFilteredProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::with(['categories', 'brands', 'images', 'variants']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category'])) {
            $query->whereHas('categories', fn ($q) => $q->where('categories._id', $filters['category']));
        }

        if (! empty($filters['brand'])) {
            $query->whereHas('brands', fn ($q) => $q->where('brands._id', $filters['brand']));
        }

        if (! empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        $sortBy = in_array($filters['sort_by'] ?? '', self::ALLOWED_ADMIN_SORT_COLUMNS, true)
            ? $filters['sort_by'] : 'created_at';
        $sortOrder = in_array(strtolower($filters['sort_order'] ?? ''), ['asc', 'desc'], true)
            ? strtolower($filters['sort_order']) : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // An unrecognised stock_status otherwise reached `where stock_status =
        // <garbage>` and blanked the page, so fall back to no stock filter.
        $stockStatus = in_array($filters['stock_status'] ?? '', ['in_stock', 'out_of_stock', 'on_backorder'], true)
            ? $filters['stock_status']
            : null;

        if ($stockStatus !== null) {
            $query->where('stock_status', $stockStatus);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage)->withQueryString();
    }

    public function getCategoriesForSelect()
    {
        return Category::active()->orderBy('name')->get();
    }

    public function getBrandsForSelect()
    {
        return Brand::orderBy('name')->get();
    }

    public function getAttributesForSelect()
    {
        // Values are eager loaded because both the product form and the variant
        // rows iterate $attribute->values; without this the page ran one query
        // per attribute. Ordered by sort_order so the admin's chosen value order
        // is what the operator sees.
        return Attribute::with('values')->orderBy('sort_order')->orderBy('name')->get();
    }

    public function toggleStatus(Product $product): Product
    {
        $oldValues = $product->toArray();
        $product->update(['is_active' => ! $product->is_active]);
        $newValues = $product->fresh()->toArray();

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'updated',
            entity: Product::class,
            entityId: $product->getKey(),
            oldValues: $oldValues,
            newValues: $newValues
        );

        return $product->fresh();
    }

    public function toggleFeatured(Product $product): Product
    {
        $oldValues = $product->toArray();
        $product->update(['is_featured' => ! $product->is_featured]);
        $newValues = $product->fresh()->toArray();

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'updated',
            entity: Product::class,
            entityId: $product->getKey(),
            oldValues: $oldValues,
            newValues: $newValues
        );

        return $product->fresh();
    }

    public function uploadImages(Product $product, array $files): void
    {
        // Validate files in service layer (defense in depth)
        foreach ($files as $file) {
            if (! $file->isValid()) {
                throw new \InvalidArgumentException('Invalid file upload.');
            }
            if ($file->getSize() > 5120 * 1024) { // 5MB
                throw new \InvalidArgumentException('Image size must not exceed 5MB.');
            }
            $mime = $file->getMimeType();
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                throw new \InvalidArgumentException('Image must be JPEG, PNG, GIF, or WebP.');
            }
        }

        $compressed = $this->compression->compressMultiple($files, 1000, 80);

        foreach ($compressed as $index => $file) {
            $path = $file->store('products', 'public');
            $product->images()->create([
                'path' => $path,
                'alt_text' => $product->name,
                'sort_order' => $index,
            ]);
        }
    }

    public function deleteImage(ProductImage $image): bool
    {
        \Storage::disk('public')->delete($image->path);

        return $image->delete();
    }

    public function setPrimaryImage(Product $product, ProductImage $image): ProductImage
    {
        abort_if($image->product_id !== $product->getKey(), 403);

        $product->images()->where('is_primary', true)->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return $image->fresh();
    }

    public function reorderImages(array $imageIds): void
    {
        foreach ($imageIds as $index => $id) {
            ProductImage::where('_id', $id)->update(['sort_order' => $index]);
        }
    }

    public function getTotalCustomers(): int
    {
        return Customer::count();
    }

    public function getTotalProducts(): int
    {
        return Product::count();
    }

    public function getTopSellingProducts(int $limit = 5)
    {
        return Product::with(['images',
            'reviews' => fn ($q) => $q->where('status', 'approved')])
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
            ->withCount(['orderItems as total_sold' => function ($q) {
                $q->select(DB::raw('SUM(quantity)'));
            }])
            ->orderBy('total_sold', 'desc')
            ->limit($limit)
            ->get();
    }
}
