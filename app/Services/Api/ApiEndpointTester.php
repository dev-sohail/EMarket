<?php

namespace App\Services\Api;

use App\Http\Controllers\Api\ApiDynamicController;
use App\Http\Middleware\ApiAuthAndAuthorization;
use App\Models\ApiEndpoint;
use App\Models\ApiEndpointParameter;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Executes a single call against a dynamically registered endpoint so an admin
 * can exercise it from the browser.
 *
 * This runs the *real* pipeline — ApiAuthAndAuthorization, then
 * ApiDynamicController — rather than calling the handler service directly. That
 * is the whole point: a direct call would skip token validation, the parameter
 * validation and the RBAC grant, so an endpoint that always 401s in production
 * would look healthy here.
 *
 * Only the two API middleware/controller layers are composed. The global web
 * middleware (session, CSRF, cookies) is deliberately skipped: this is an API
 * call, not a browser form post, and running CSRF over it would reject every
 * write-method test for reasons that have nothing to do with the endpoint.
 *
 * The sub-request is a genuine call and is recorded in `api_route_logs` by the
 * controller like any other.
 */
class ApiEndpointTester
{
    /**
     * Guards against a test payload being large enough to be a denial of
     * service against the tester itself.
     */
    protected const MAX_BODY_BYTES = 64 * 1024;

    /**
     * Run one call and return everything the UI needs to show the result.
     *
     * @param  array{path?: array<string, string>, query?: array<string, mixed>, headers?: array<string, string>, body?: string|null, token?: string|null}  $input
     * @return array{request: array<string, mixed>, response: array<string, mixed>|null, error: string|null}
     */
    public function run(ApiEndpoint $endpoint, array $input = []): array
    {
        $endpoint->loadMissing('parameters');

        $uri = $this->buildUri($endpoint, $input['path'] ?? []);

        $request = $this->buildRequest($endpoint, $uri, $input);

        $route = $this->bindRoute($endpoint, $request);

        // The route has to be attached before the middleware runs, not just
        // before the controller: ApiAuthAndAuthorization resolves the endpoint
        // from the matched route *name* to decide whether the call needs a
        // token. A request with no route resolves no endpoint, which falls
        // through to "authenticate" and 401s every public endpoint.
        $request->setRouteResolver(fn () => $route);

        $startedAt = microtime(true);

        try {
            $response = (new ApiAuthAndAuthorization)->handle($request, fn (Request $request) => (new ApiDynamicController)->__invoke($request));
        } catch (\Throwable $e) {
            Log::error('API endpoint tester failure: {message}', [
                'endpoint' => $endpoint->_id,
                'exception' => $e,
            ]);

            return [
                'request' => $this->describeRequest($endpoint, $request),
                'response' => null,
                // Static copy: the message of a QueryException carries the SQL,
                // bindings and table names. The full exception is in the log.
                'error' => 'The endpoint raised an unexpected error. See the application log for details.',
            ];
        }

        return [
            'request' => $this->describeRequest($endpoint, $request),
            'response' => [
                'status' => $response->getStatusCode(),
                'status_text' => Response::$statusTexts[$response->getStatusCode()] ?? '',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'headers' => $this->describeHeaders($response->headers->all()),
                'body' => $this->prettyBody($response),
            ],
            'error' => null,
        ];
    }

    /**
     * Substitute admin-supplied values into the endpoint's stored path.
     *
     * A path parameter left blank gets a placeholder rather than an empty
     * segment: `store/products/` would not match the registered route at all,
     * and the tester would report a 404 that looks like a broken endpoint
     * rather than an unfilled form field.
     *
     * @param  array<string, string>  $values
     */
    protected function buildUri(ApiEndpoint $endpoint, array $values): string
    {
        $uri = '/'.trim($endpoint->path, '/');

        foreach ($endpoint->parameters as $parameter) {
            if ($parameter->location !== 'path') {
                continue;
            }

            $value = trim((string) ($values[$parameter->name] ?? ''));

            if ($value === '') {
                $value = $parameter->default_value ?: '1';
            }

            $uri = str_replace('{'.$parameter->name.'}', rawurlencode($value), $uri);
        }

        return $uri;
    }

    /**
     * Build the synthetic request, mirroring what the router would hand the
     * API stack.
     *
     * @param  array<string, mixed>  $input
     */
    protected function buildRequest(ApiEndpoint $endpoint, string $uri, array $input): Request
    {
        $query = $this->stringKeyed($input['query'] ?? []);
        $headers = $this->stringKeyed($input['headers'] ?? []);

        $body = $this->body($input['body'] ?? null);
        $content = $body === '' ? null : $body;

        $server = [
            'REQUEST_METHOD' => strtoupper($endpoint->method),
            'REQUEST_URI' => $uri,
            'QUERY_STRING' => http_build_query($query),
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'HTTP_HOST' => 'localhost',
            'HTTP_ACCEPT' => 'application/json',
        ];

        // Default a JSON content type so a body is parsed as JSON rather than
        // form-encoded; the admin can override it from the headers box.
        if ($content !== null && ! $this->hasHeader($headers, 'content-type')) {
            $headers['Content-Type'] = 'application/json';
        }

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        if ($content !== null) {
            $server['CONTENT_TYPE'] = $headers['Content-Type'] ?? $server['CONTENT_TYPE'] ?? 'application/json';
            $server['CONTENT_LENGTH'] = (string) strlen($content);
        }

        $token = trim((string) ($input['token'] ?? ''));

        if ($token !== '') {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        return Request::create($uri, strtoupper($endpoint->method), $query, [], [], $server, $content);
    }

    /**
     * Attach a Route to the request so the controller can recover the endpoint
     * id from the route name and the path parameters from the binding.
     *
     * The registrar names routes `api.dynamic.{endpointId}` and the controller
     * decodes the id from that name, so the tester has to reproduce the same
     * naming rather than invent its own.
     */
    protected function bindRoute(ApiEndpoint $endpoint, Request $request): Route
    {
        $route = new Route([strtoupper($endpoint->method)], trim($endpoint->path, '/'), [ApiDynamicController::class]);
        $route->name('api.dynamic.'.$endpoint->_id);

        // Compile and bind so `parameterNames()`/`parameter()` resolve, exactly
        // as they do for a real matched route.
        $route->bind($request);

        return $route;
    }

    /**
     * The values the UI shows for the request it just sent.
     *
     * @return array<string, mixed>
     */
    protected function describeRequest(ApiEndpoint $endpoint, Request $request): array
    {
        return [
            'method' => strtoupper($endpoint->method),
            'url' => rtrim(config('api_management.prefix', 'api'), '/').'/'.ltrim($request->path(), '/'),
            'path_params' => $this->pathParameterValues($endpoint, $request),
            'query' => $request->query(),
            'body' => $this->body($request->getContent()),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function pathParameterValues(ApiEndpoint $endpoint, Request $request): array
    {
        return $endpoint->parameters
            ->where('location', 'path')
            ->mapWithKeys(fn (ApiEndpointParameter $parameter) => [
                $parameter->name => (string) $request->route($parameter->name),
            ])
            ->all();
    }

    /**
     * Pretty-print a JSON response so it is readable in the browser, and fall
     * back to the raw body for anything that is not JSON.
     */
    protected function prettyBody(Response $response): string
    {
        $body = $response->getContent();

        if ($body === false || $body === '') {
            return '';
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return Str::limit($body, 4000);
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $responseHeaders
     * @return array<string, string>
     */
    protected function describeHeaders(array $responseHeaders): array
    {
        $interesting = ['content-type', 'cache-control', 'x-content-type-options'];

        $described = [];

        foreach ($responseHeaders as $name => $values) {
            if (in_array(strtolower($name), $interesting, true)) {
                $described[$name] = implode(', ', $values);
            }
        }

        return $described;
    }

    /**
     * Accept the textarea's `key=value` / `key: value` lines as a map.
     *
     * @return array<string, mixed>
     */
    public function parseKeyValueLines(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [];
        }

        $parsed = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $separator = str_contains($line, '=') ? '=' : (str_contains($line, ':') ? ':' : null);

            if ($separator === null) {
                // A bare key is a flag: `in_stock` means `in_stock=1`.
                $parsed[$line] = '1';

                continue;
            }

            [$key, $value] = explode($separator, $line, 2);

            $key = trim($key);

            if ($key !== '') {
                $parsed[$key] = trim($value);
            }
        }

        return $parsed;
    }

    /**
     * Clamp the request body and normalise it to a string.
     */
    protected function body(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }

        return Str::limit($raw, self::MAX_BODY_BYTES, '');
    }

    /**
     * Keep only string keys — a numeric key would silently become an array
     * index and never match a declared parameter name.
     *
     * @param  array<mixed, mixed>  $values
     * @return array<string, mixed>
     */
    protected function stringKeyed(array $values): array
    {
        $filtered = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && $key !== '') {
                $filtered[$key] = is_scalar($value) || $value === null ? (string) $value : $value;
            }
        }

        return $filtered;
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function hasHeader(array $headers, string $name): bool
    {
        foreach (array_keys($headers) as $key) {
            if (strcasecmp($key, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
