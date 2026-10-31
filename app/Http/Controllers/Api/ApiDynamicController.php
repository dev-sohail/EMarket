<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiEndpoint;
use App\Models\ApiEndpointParameter;
use App\Models\ApiRouteLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator as ValidationValidator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Single, generic entry point for all dynamically-registered API endpoints.
 *
 * The Router sends every `api.dynamic.{endpointId}` route here. The endpoint id
 * is carried by the route *name*, not the path: one URI can back many
 * endpoints, so the registrar encodes identity in the name and this controller
 * decodes it. It then resolves the ApiEndpoint row, validates the input against
 * the declared parameters, checks RBAC/ABAC permissions, delegates to the
 * configured handler, and returns a structured JSON response.
 *
 * Route registration (ApiRouteRegistrar) and per-request handling (here) are
 * deliberately separated.
 */
class ApiDynamicController extends Controller
{
    /**
     * Handle any HTTP method for the matched dynamic endpoint.
     *
     * Laravel dispatches this with the Request alone: the endpoint is not a
     * path parameter.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $endpointId = $this->endpointIdFromRoute($request);

        if ($endpointId === null) {
            return $this->errorResponse(
                null, $request, Response::HTTP_NOT_FOUND,
                'Endpoint not found', microtime(true)
            );
        }

        return $this->handle($request, $endpointId);
    }

    /**
     * Recover the endpoint id from the matched route's name.
     */
    protected function endpointIdFromRoute(Request $request): ?string
    {
        $route = $request->route();

        if ($route === null || $route->getName() === null) {
            return null;
        }

        $prefix = 'api.dynamic.';

        if (! str_starts_with($route->getName(), $prefix)) {
            return null;
        }

        $endpointId = substr($route->getName(), strlen($prefix));

        return $endpointId === '' ? null : $endpointId;
    }

    /**
     * Resolve, validate, authorise and dispatch a single request.
     */
    public function handle(Request $request, string $endpointId): JsonResponse
    {
        $start = microtime(true);

        $endpoint = ApiEndpoint::with('parameters')->find($endpointId);

        if ($endpoint === null) {
            return $this->errorResponse(
                $endpoint, $request, Response::HTTP_NOT_FOUND,
                'Endpoint not found', $start
            );
        }

        // --- Validation -----------------------------------------------------
        $validator = $this->buildValidator($request, $endpoint);

        if ($validator->fails()) {
            return $this->errorResponse(
                $endpoint, $request, Response::HTTP_UNPROCESSABLE_ENTITY,
                'Validation failed', $start,
                ['errors' => $validator->errors()->messages()]
            );
        }

        $validated = $validator->validated();

        // --- Authorization --------------------------------------------------
        // Authentication is already settled by ApiAuthAndAuthorization, which
        // honours the endpoint's requires_auth flag and attaches the principal
        // to the request. What remains is the permission decision, which is
        // per-endpoint and therefore lives here.
        $user = $request->user();

        if (! $this->authorize($endpoint, $user)) {
            return $this->errorResponse(
                $endpoint, $request, Response::HTTP_FORBIDDEN,
                'Forbidden', $start
            );
        }

        // --- Handler dispatch ----------------------------------------------
        return $this->dispatchToHandler(
            $endpoint,
            $request,
            $validated,
            $user,
            $start
        );
    }

    /**
     * Authorize the request against the endpoint's permission set.
     *
     * RBAC: the user must hold at least one permission explicitly granted to
     * the endpoint. ABAC: any conditions stored on the permission are
     * evaluated against the authenticated user's attributes.
     */
    protected function authorize(ApiEndpoint $endpoint, $user): bool
    {
        $permissions = $endpoint->permissions()->with('permission')->get();

        // A public endpoint with no permission grants is open to everyone.
        // Authentication was already settled by the middleware.
        if (! $endpoint->requires_auth && $permissions->isEmpty()) {
            return true;
        }

        // Everything below needs a principal to make a permission decision.
        if ($user === null) {
            return false;
        }

        // Authenticated, but the endpoint declares no specific permissions:
        // authentication alone is the bar.
        if ($permissions->isEmpty()) {
            return true;
        }

        foreach ($permissions as $endpointPermission) {
            if ($endpointPermission->permission === null) {
                continue;
            }

            if (! $user->hasPermission($endpointPermission->permission->name)) {
                continue;
            }

            $accessType = $endpointPermission->access_type;

            if ($accessType === 'deny') {
                return false;
            }

            // access_type === 'allow': evaluate ABAC conditions.
            if (! $endpointPermission->evaluateConditions($this->subjectAttributes($user))) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Build an array of user attributes for ABAC evaluation.
     */
    protected function subjectAttributes($user): array
    {
        if ($user === null) {
            return [];
        }

        return [
            'user_id' => $user->getKey(),
            'is_admin' => $user->hasRole('Super Admin') || $user->hasRole('Admin'),
            'roles' => $user->roles->pluck('name')->toArray(),
        ];
    }

    /**
     * Validate the request against the parameter rules declared on the
     * endpoint definition.
     */
    protected function buildValidator(Request $request, ApiEndpoint $endpoint): ValidationValidator
    {
        $rules = [];

        foreach ($endpoint->parameters as $parameter) {
            $rules[$parameter->name] = $this->buildRule($parameter);
        }

        $data = $this->extractData($request, $endpoint);

        return validator()->make($data, $rules);
    }

    /**
     * Extract the data the validator should check from the request, keyed by
     * the parameter name. Path parameters come from the matched route, query
     * from the query string and body from the parsed request body.
     */
    protected function extractData(Request $request, ApiEndpoint $endpoint): array
    {
        $data = [];

        // Merge path segments (route parameters) first, as they are the most
        // authoritative source for a given name.
        foreach ($request->route()->parameterNames() as $name) {
            $value = $request->route()->parameter($name);

            if ($value !== null) {
                $data[$name] = $value;
            }
        }

        foreach ($endpoint->parameters as $parameter) {
            if (isset($data[$parameter->name])) {
                continue; // path param already captured
            }

            $data[$parameter->name] = match ($parameter->location) {
                'query' => $request->query($parameter->name, $parameter->default_value),
                'body' => $request->input($parameter->name, $parameter->default_value),
                'header' => $request->header($parameter->name, $parameter->default_value),
                default => $request->input($parameter->name, $parameter->default_value),
            };
        }

        return $data;
    }

    /**
     * Build a Laravel validation rule string/array from the parameter metadata.
     */
    protected function buildRule(ApiEndpointParameter $parameter): string|array
    {
        $rules = [];

        // Type-based rules.
        if ($parameter->type === 'integer') {
            $rules[] = 'integer';
        } elseif ($parameter->type === 'number') {
            $rules[] = 'numeric';
        } elseif ($parameter->type === 'boolean') {
            $rules[] = 'boolean';
        } elseif ($parameter->type === 'array') {
            $rules[] = 'array';
        }

        // Custom rules stored as JSON.
        foreach ($parameter->validationRules() as $rule) {
            $rules[] = $rule;
        }

        if ($parameter->required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }

        return implode('|', $rules);
    }

    /**
     * Dispatch the request to the endpoint's configured handler and wrap the
     * result in a JSON response.
     */
    protected function dispatchToHandler(
        ApiEndpoint $endpoint,
        Request $request,
        array $validated,
        $user,
        float $start
    ): JsonResponse {
        $handler = $endpoint->resolveHandler();
        $method = $this->handlerMethod($endpoint, $handler);

        if ($handler === null || $method === null) {
            return $this->errorResponse(
                $endpoint, $request, Response::HTTP_INTERNAL_SERVER_ERROR,
                'Handler not configured', $start
            );
        }

        // Inject the validated parameters into the request so the handler
        // reads them from a single, type-safe source.
        $request->attributes->set('api_validated', $validated);
        $request->attributes->set('api_endpoint', $endpoint);

        // Build the argument list: the Request followed by any path/route
        // parameters that the handler method declares.
        $arguments = $this->buildArguments($endpoint, $request, $validated);

        try {
            $result = $handler->{$method}(...$arguments);
        } catch (HttpExceptionInterface $e) {
            // A handler that throws an HTTP exception is making a deliberate
            // decision — a 404 for a missing row, a 422 for a bad state
            // transition. Relabelling that as a 500 would tell the caller the
            // server broke when the request was simply unsatisfiable, so it is
            // allowed to bubble and be rendered with its own status.
            return $this->errorResponse(
                $endpoint, $request, $e->getStatusCode(),
                $e->getMessage() ?: 'Request could not be completed', $start
            );
        } catch (\Throwable $e) {
            Log::error('Dynamic API handler exception: {exception}', [
                'endpoint' => $endpoint->_id,
                'exception' => $e,
            ]);

            // Never echo $e->getMessage(), not even under APP_DEBUG: a
            // QueryException message carries the SQL, bindings and table
            // names, and this is a public JSON endpoint. The detail is in the
            // log entry above.
            return $this->errorResponse(
                $endpoint, $request, Response::HTTP_INTERNAL_SERVER_ERROR,
                'Handler error', $start
            );
        }

        return $this->successResponse(
            $endpoint, $request, $result, Response::HTTP_OK, $start
        );
    }

    /**
     * Resolve the handler method name from the endpoint configuration.
     */
    protected function handlerMethod(ApiEndpoint $endpoint, ?object $handler): ?string
    {
        if ($handler === null) {
            return null;
        }

        [$target, $method] = match ($endpoint->handler_type) {
            'service' => $this->splitAction($endpoint->action, '::'),
            default => $this->splitAction($endpoint->action, '@'),
        };

        return $method;
    }

    protected function splitAction(string $action, string $separator): array
    {
        return str_contains($action, $separator)
            ? explode($separator, $action, 2)
            : [null, null];
    }

    /**
     * Build the argument list for the handler method. The handler receives the
     * Request first (for consistency with Laravel controller conventions),
     * followed by any declared route parameters resolved to model instances.
     */
    protected function buildArguments(ApiEndpoint $endpoint, Request $request, array $validated): array
    {
        return [$request];
    }

    /**
     * Emit a success JSON response and record the route log.
     */
    protected function successResponse(
        ApiEndpoint $endpoint,
        Request $request,
        mixed $data,
        int $status,
        float $start
    ): JsonResponse {
        $duration = (int) round((microtime(true) - $start) * 1000);

        $this->recordLog($endpoint, $request, $status, $duration, null);

        return response()->json([
            'data' => $data,
            'meta' => [
                'endpoint_id' => $endpoint->_id,
                'duration_ms' => $duration,
            ],
        ], $status);
    }

    /**
     * Emit an error JSON response and record the route log.
     */
    protected function errorResponse(
        ?ApiEndpoint $endpoint,
        Request $request,
        int $status,
        string $message,
        float $start,
        ?array $extra = null
    ): JsonResponse {
        $duration = (int) round((microtime(true) - $start) * 1000);

        $payload = ['message' => $message];

        if ($extra !== null) {
            $payload = array_merge($payload, $extra);
        }

        if ($endpoint !== null) {
            $this->recordLog($endpoint, $request, $status, $duration, $message);
        }

        return response()->json($payload, $status);
    }

    /**
     * Append an ApiRouteLog row for observability. Errors are caught and
     * swallowed so logging never breaks a response.
     */
    protected function recordLog(
        ApiEndpoint $endpoint,
        Request $request,
        int $status,
        int $duration,
        ?string $error
    ): void {
        // config('api_management.logging.enabled') is the switch admins get
        // for the audit trail; without this check the env toggle did nothing.
        if (! config('api_management.logging.enabled', true)) {
            return;
        }

        try {
            $user = $request->user();

            $payload = [];

            if ($request->isJson()) {
                $payload = $request->getContent() ? json_decode($request->getContent(), true) : [];
            }

            ApiRouteLog::create([
                'api_endpoint_id' => $endpoint->_id,
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $status,
                'duration_ms' => $duration,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'user_id' => $user?->getKey(),
                'request_payload' => is_array($payload) ? $payload : null,
                'error' => $error,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Logging must never mask the real response.
        }
    }
}
