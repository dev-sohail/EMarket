<?php

namespace App\Services\Audit;

use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Models\User;

/**
 * Records API management changes in the audit log.
 *
 * The API surface is the most security-relevant thing an admin can change here:
 * activating an endpoint publishes a live URL, and reflection publishes a whole
 * API from a model in one action. None of it was audited before, so the log
 * could not answer "who turned this on, and when".
 *
 * Payloads are built by hand rather than dumped with `toArray()`, because the
 * useful question about an API change is not "what did the row look like" but
 * "which URLs are now reachable and what can be sent to them". A generated
 * definition carries its originating model and the filters it exposes, which is
 * the only way to explain later why a given query parameter was accepted.
 */
class ApiManagementAuditor
{
    /**
     * Definition attributes worth recording. Everything else on the row —
     * `_id`, `created_by`, timestamps — is either noise or derivable.
     *
     * @var list<string>
     */
    protected const DEFINITION_FIELDS = ['name', 'slug', 'description', 'source', 'is_active'];

    /**
     * @var list<string>
     */
    protected const ENDPOINT_FIELDS = [
        'method', 'path', 'action', 'handler_type', 'description',
        'sort_order', 'requires_auth', 'is_active',
    ];

    public function __construct(protected AuditService $audit) {}

    // ------------------------------------------------------------ definitions

    public function definitionCreated(ApiDefinition $definition): void
    {
        $this->log('created', $definition, null, $this->persistedSnapshot($definition));
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    public function definitionUpdated(ApiDefinition $definition, ?array $before = null): void
    {
        $this->log('updated', $definition, $before, $this->snapshotDefinition($definition));
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    public function definitionDeleted(ApiDefinition $definition, ?array $before = null): void
    {
        $this->log('deleted', $definition, $before, null);
    }

    // -------------------------------------------------------------- endpoints

    public function endpointCreated(ApiEndpoint $endpoint): void
    {
        $this->log('created', $endpoint, null, $this->persistedSnapshot($endpoint));
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    public function endpointUpdated(ApiEndpoint $endpoint, ?array $before = null): void
    {
        $this->log('updated', $endpoint, $before, $this->snapshotEndpoint($endpoint));
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    public function endpointDeleted(ApiEndpoint $endpoint, ?array $before = null): void
    {
        $this->log('deleted', $endpoint, $before, null);
    }

    // ------------------------------------------------------------- reflection

    /**
     * A definition generated from a model for the first time.
     *
     * @param  array<string, mixed>  $summary
     */
    public function definitionReflected(ApiDefinition $definition, string $model, array $summary): void
    {
        $this->log('reflected', $definition, null, $this->snapshotDefinition($definition) + [
            'reflected_model' => $model,
        ] + $summary);
    }

    /**
     * A definition regenerated from its model.
     *
     * The "before" snapshot is the point of this entry. Regeneration overwrites
     * the fields the reflector owns, so a hand edit to the name or description
     * is silently reverted — and the only record that the revert happened is
     * the difference between the two states recorded here.
     *
     * @param  array<string, mixed>  $summary
     */
    public function definitionRegenerated(ApiDefinition $definition, string $model, ?array $before = null, array $summary = []): void
    {
        $this->log('regenerated', $definition, $before, $this->snapshotDefinition($definition) + [
            'reflected_model' => $model,
        ] + $summary);
    }

    // --------------------------------------------------------------- snapshots

    /**
     * @return array<string, mixed>
     */
    public function snapshotDefinition(ApiDefinition $definition): array
    {
        return $definition->only(self::DEFINITION_FIELDS);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotEndpoint(ApiEndpoint $endpoint): array
    {
        return $endpoint->only(self::ENDPOINT_FIELDS);
    }

    /**
     * Snapshot a just-created row *as stored*, not as it was assembled.
     *
     * A model straight out of `create()` only holds the attributes the caller
     * passed. Anything left to a database default is absent from it, so
     * snapshotting immediately would record `null` for a column that is
     * actually set — an audit entry claiming an endpoint needs no
     * authentication when the stored row requires it. Re-reading is what makes
     * the log describe the row that really exists.
     *
     * @template T of ApiDefinition|ApiEndpoint
     *
     * @param  T  $subject
     * @return array<string, mixed>
     */
    protected function persistedSnapshot(ApiDefinition|ApiEndpoint $subject): array
    {
        $subject->refresh();

        return $subject instanceof ApiDefinition
            ? $this->snapshotDefinition($subject)
            : $this->snapshotEndpoint($subject);
    }

    /**
     * The facts about a generated API that a later reader needs in order to
     * understand it: where it is served, and what it accepts.
     *
     * @return array<string, mixed>
     */
    public function reflectSummary(ApiDefinition $definition): array
    {
        $endpoints = $definition->endpoints()->with('parameters')->get();

        return [
            'paths' => $endpoints->pluck('path')->all(),
            'endpoint_count' => $endpoints->count(),
            'parameter_count' => $endpoints->sum(fn (ApiEndpoint $endpoint) => $endpoint->parameters->count()),
            // The generated surface is public unless someone has since changed
            // it, which is exactly what an auditor wants to know.
            'public_endpoints' => $endpoints->where('requires_auth', false)->pluck('path')->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function log(string $action, ApiDefinition|ApiEndpoint $subject, ?array $old, ?array $new): void
    {
        $this->audit->log(
            user: $this->actor(),
            action: $action,
            entity: $subject::class,
            entityId: $subject->getKey(),
            oldValues: $old,
            newValues: $new,
        );
    }

    /**
     * The admin who made the change.
     *
     * Null for a console/CLI caller, which the audit screen renders as
     * "System" — the same convention as every other audited action.
     */
    protected function actor(): ?User
    {
        return auth()->guard('admin')->user();
    }
}
