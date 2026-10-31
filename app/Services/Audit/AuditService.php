<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditService
{
    public function __construct(
        protected AuditLog $auditLog
    ) {}

    /**
     * Record one audited action.
     *
     * `old_values`/`new_values` are handed to the model as arrays and encoded by
     * its `array` cast. They used to be json_encode()d here first, which
     * double-encoded them: the cast encoded the already-encoded *string*, so
     * reading the row back produced a JSON string rather than an array. That is
     * invisible on the list screen and breaks the detail screen outright, since
     * it calls array_keys() on the payload. Do not pre-encode here.
     */
    public function log(
        ?User $user,
        string $action,
        string $entity,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        return $this->auditLog->create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'entity_type' => $entity,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function getLogs(array $filters = []): LengthAwarePaginator
    {
        $query = $this->auditLog->with('user');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (! empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('entity_type', 'like', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'] ?? 20);
    }
}
