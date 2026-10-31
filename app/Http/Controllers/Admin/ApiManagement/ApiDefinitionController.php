<?php

namespace App\Http\Controllers\Admin\ApiManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApiManagement\StoreApiDefinitionRequest;
use App\Http\Requests\Admin\ApiManagement\UpdateApiDefinitionRequest;
use App\Models\ApiDefinition;
use App\Services\Audit\ApiManagementAuditor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiDefinitionController extends Controller
{
    public function __construct(protected ApiManagementAuditor $auditor) {}

    public function index(Request $request): View
    {
        // Ordered by name, not `sort_order`: the api_definitions table has no
        // such column (see the create_api_management_tables migration), so
        // ordering by it threw "Unknown column 'sort_order' in 'order clause'"
        // and the whole API Management screen 500'd.
        $definitions = ApiDefinition::withCount('endpoints')
            ->orderBy('name')
            ->get();

        return view('admin.api-management.definitions.index', compact('definitions'));
    }

    public function create(): View
    {
        return view('admin.api-management.definitions.create');
    }

    public function store(StoreApiDefinitionRequest $request)
    {
        // definitionAttributes() rather than validated(): it is the only place
        // `source` is set, and it is set by the system rather than the form.
        $definition = ApiDefinition::create($request->definitionAttributes());

        $this->auditor->definitionCreated($definition);

        return redirect()
            ->route('admin.api-management.definitions.edit', $definition)
            ->with('success', 'API definition created successfully.');
    }

    /**
     * Read-only view of everything a definition contains.
     *
     * Loads the whole graph in one go — endpoints, their parameters and their
     * permission grants — because the screen is a read summary and an N+1 per
     * row here would be paid on every page view.
     */
    public function show(ApiDefinition $apiDefinition): View
    {
        $definition = ApiDefinition::query()
            ->with([
                'endpoints' => fn ($query) => $query
                    ->with(['parameters', 'permissions.permission'])
                    ->orderBy('sort_order'),
            ])
            ->findOrFail($apiDefinition->getKey());

        return view('admin.api-management.definitions.show', [
            'definition' => $definition,
            // A tester result, present only immediately after a test run.
            'testResult' => session('test_result'),
            'testEndpointId' => session('test_endpoint_id'),
        ]);
    }

    public function edit(ApiDefinition $apiDefinition): View
    {
        return view('admin.api-management.definitions.edit', [
            'definition' => $apiDefinition,
        ]);
    }

    public function update(UpdateApiDefinitionRequest $request, ApiDefinition $apiDefinition)
    {
        // Captured before the write so the log records the real before/after
        // rather than two identical states.
        $before = $this->auditor->snapshotDefinition($apiDefinition);

        $apiDefinition->update($request->definitionAttributes());

        $this->auditor->definitionUpdated($apiDefinition, $before);

        return redirect()
            ->route('admin.api-management.definitions.edit', $apiDefinition)
            ->with('success', 'API definition updated successfully.');
    }

    public function destroy(ApiDefinition $apiDefinition)
    {
        $before = $this->auditor->snapshotDefinition($apiDefinition);

        $apiDefinition->delete();

        $this->auditor->definitionDeleted($apiDefinition, $before);

        return redirect()
            ->route('admin.api-management.definitions.index')
            ->with('success', 'API definition deleted successfully.');
    }
}
