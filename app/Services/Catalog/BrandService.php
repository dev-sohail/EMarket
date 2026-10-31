<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BrandService
{
    public function __construct(
        protected Brand $brand,
        protected NotificationService $notificationService,
        protected AuditService $auditService
    ) {}

    public function getAll(): Collection
    {
        return Brand::with('products')
            ->active()
            ->orderBy('sort_order')
            ->get();
    }

    public function getBySlug(string $slug): Brand
    {
        return Brand::with('products')
            ->where('slug', $slug)
            ->active()
            ->firstOrFail();
    }

    public function create(array $data): Brand
    {
        return DB::transaction(function () use ($data) {
            $brand = Brand::create($data);

            CatalogCache::forgetBrands();

            $this->notificationService->sendToAllAdmins(
                title: 'Brand Created',
                message: "Brand '{$brand->name}' has been created.",
                data: ['brand_id' => $brand->getKey()]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'created',
                entity: Brand::class,
                entityId: $brand->getKey(),
                newValues: $brand->toArray()
            );

            return $brand;
        });
    }

    public function update(string $id, array $data): Brand
    {
        return DB::transaction(function () use ($id, $data) {
            $brand = Brand::findOrFail($id);
            $oldValues = $brand->toArray();
            $brand->update($data);

            CatalogCache::forgetBrands();

            $this->notificationService->sendToAllAdmins(
                title: 'Brand Updated',
                message: "Brand '{$brand->name}' has been updated.",
                data: ['brand_id' => $brand->getKey()]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'updated',
                entity: Brand::class,
                entityId: $brand->getKey(),
                oldValues: $oldValues,
                newValues: $brand->fresh()->toArray()
            );

            return $brand;
        });
    }

    public function toggleStatus(Brand $brand): Brand
    {
        $oldValues = $brand->toArray();
        $brand->update(['is_active' => ! $brand->is_active]);

        CatalogCache::forgetBrands();

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'updated',
            entity: Brand::class,
            entityId: $brand->getKey(),
            oldValues: $oldValues,
            newValues: $brand->fresh()->toArray()
        );

        return $brand;
    }

    public function delete(string $id): bool
    {
        $brand = Brand::findOrFail($id);

        if ($brand->products()->count() > 0) {
            throw new \Exception('Cannot delete brand with associated products.');
        }

        $brandData = $brand->toArray();
        $deleted = $brand->delete();

        CatalogCache::forgetBrands();

        if ($deleted) {
            $this->notificationService->sendToAllAdmins(
                title: 'Brand Deleted',
                message: "Brand '{$brandData['name']}' has been deleted.",
                data: ['brand_id' => $id]
            );

            $this->auditService->log(
                user: auth()->guard('admin')->user(),
                action: 'deleted',
                entity: Brand::class,
                entityId: $id,
                oldValues: $brandData
            );
        }

        return $deleted;
    }
}
