<?php

namespace App\Http\Controllers\Admin\ApiManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApiManagement\StoreApiEndpointRequest;
use App\Http\Requests\Admin\ApiManagement\TestApiEndpointRequest;
use App\Http\Requests\Admin\ApiManagement\UpdateApiEndpointRequest;
use App\Models\ApiDefinition;
use App\Models\ApiEndpoint;
use App\Services\Api\ApiEndpointTester;
use App\Services\Audit\ApiManagementAuditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ApiEndpointController extends Controller
{
    public function __construct(protected ApiManagementAuditor $auditor) {}

    /**
     * Endpoints are always managed from inside their definition, so there is no
     * standalone index: `create` renders the form and `edit` the same form with
     * the endpoint pre-filled.
     */
    public function create(ApiDefinition $apiDefinition): View
    {
        return view('admin.api-management.endpoints.create', [
            'definition' => $apiDefinition,
            'endpoint' => null,
        ]);
    }

    public function store(StoreApiEndpointRequest $request, ApiDefinition $apiDefinition): RedirectResponse
    {
        $endpoint = $apiDefinition->endpoints()->create(
            $request->validated() + ['api_definition_id' => $apiDefinition->getKey()]
        );

        $this->auditor->endpointCreated($endpoint);

        return redirect()
            ->route('admin.api-management.definitions.edit', $apiDefinition)
            ->with('success', sprintf('Endpoint %s %s created.', $endpoint->method, $endpoint->path))
            ->with('route_cache_notice', true);
    }

    public function edit(ApiDefinition $apiDefinition, ApiEndpoint $apiEndpoint): View
    {
        $this->assertBelongsTo($apiDefinition, $apiEndpoint);

        return view('admin.api-management.endpoints.edit', [
            'definition' => $apiDefinition,
            'endpoint' => $apiEndpoint,
        ]);
    }

    public function update(UpdateApiEndpointRequest $request, ApiDefinition $apiDefinition, ApiEndpoint $apiEndpoint): RedirectResponse
    {
        $this->assertBelongsTo($apiDefinition, $apiEndpoint);

        $before = $this->auditor->snapshotEndpoint($apiEndpoint);

        $apiEndpoint->update($request->validated());

        $this->auditor->endpointUpdated($apiEndpoint, $before);

        return redirect()
            ->route('admin.api-management.definitions.edit', $apiDefinition)
            ->with('success', 'Endpoint updated.')
            ->with('route_cache_notice', true);
    }

    public function destroy(ApiDefinition $apiDefinition, ApiEndpoint $apiEndpoint): RedirectResponse
    {
        $this->assertBelongsTo($apiDefinition, $apiEndpoint);

        $before = $this->auditor->snapshotEndpoint($apiEndpoint);

        $apiEndpoint->delete();

        $this->auditor->endpointDeleted($apiEndpoint, $before);

        return redirect()
            ->route('admin.api-management.definitions.edit', $apiDefinition)
            ->with('success', 'Endpoint deleted.')
            ->with('route_cache_notice', true);
    }

    /**
     * Execute one call against a registered endpoint and render the outcome on the
     * definition's view screen.
     *
     * The result is carried through the session rather than the query string so a
     * response body — which can be a whole page of products — never lands in a URL,
     * an access log or the browser history.
     */
    public function test(
        TestApiEndpointRequest $request,
        ApiDefinition $apiDefinition,
        ApiEndpoint $apiEndpoint
    ): RedirectResponse {
        $this->assertBelongsTo($apiDefinition, $apiEndpoint);

        $tester = app(ApiEndpointTester::class);

        $result = $tester->run($apiEndpoint->load('parameters'), [
            'path' => $request->validated('path', []),
            'query' => $tester->parseKeyValueLines($request->input('query')),
            'headers' => $tester->parseKeyValueLines($request->input('headers')),
            'body' => $request->input('body'),
            'token' => $request->input('token'),
        ]);

        return redirect()
            ->route('admin.api-management.definitions.show', $apiDefinition)
            ->with('test_endpoint_id', $apiEndpoint->getKey())
            ->with('test_result', $result);
    }

    /**
     * Endpoints are routed under their definition, so a mismatched pair would
     * otherwise let an endpoint be edited or deleted through the wrong API.
     */
    protected function assertBelongsTo(ApiDefinition $apiDefinition, ApiEndpoint $apiEndpoint): void
    {
        abort_unless(
            $apiEndpoint->api_definition_id === $apiDefinition->getKey(),
            404
        );
    }
}
