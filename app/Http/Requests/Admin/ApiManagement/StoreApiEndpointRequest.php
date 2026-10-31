<?php

namespace App\Http\Requests\Admin\ApiManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            // Constrained to the allow-list so an unusable endpoint is rejected
            // at the form rather than silently 500ing on every request.
            // ApiEndpoint::resolveHandler() re-checks the same list at dispatch
            // time; that runtime check is the security boundary, this one is
            // only so the operator gets told immediately. The separator is
            // matched on handler_type, so a 'controller' action needs '@' and a
            // 'service' action needs '::'.
            'action' => [
                'required',
                'string',
                'max:255',
                Rule::in(config('api_management.allowed_handlers', [])),
            ],
            // Only the two handler types ApiEndpoint::resolveHandler actually
            // dispatches on. `query_builder` and `action` were previously
            // accepted here but reach the dispatcher as a bare action string
            // with no Class@method or Class::method separator, so every such
            // endpoint failed at request time with a 500.
            'handler_type' => ['required', 'string', Rule::in(['service', 'controller'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'requires_auth' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            // Mirrors the api_endpoints_method_path_unique index. The index spans
            // (method, path, deleted_at), and soft-deleted rows share a non-NULL
            // deleted_at, so restricting to live rows is what actually keeps the
            // live pair unique. Without whereNull a soft-deleted endpoint would
            // still block the new one even though the index would allow it.
            'path' => [
                'required',
                'string',
                'max:500',
                $this->pathRule(),
                Rule::unique('api_endpoints', 'path')
                    ->where(fn ($query) => $query->where('method', $this->input('method'))->whereNull('deleted_at')),
            ],
        ];
    }

    /**
     * The path becomes a live URL via ApiRouteRegistrar, so restrict it to
     * characters that are meaningful in a path: lowercase alphanumerics, dashes,
     * underscores, forward slashes, plus Laravel's {param} placeholders.
     *
     * Validated here rather than left to the registrar: a bad path is swallowed
     * by the registrar's try/catch and the endpoint silently never becomes a
     * route, which is far harder to diagnose than a rejected form.
     */
    protected function pathRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $pattern = '#^
                (?:[a-z0-9_-]+|\{[a-zA-Z_][a-zA-Z0-9_]*\})
                (?:/(?:[a-z0-9_-]+|\{[a-zA-Z_][a-zA-Z0-9_]*\}))*
            $#x';

            if (! preg_match($pattern, trim($value))) {
                $fail('The path may only contain lowercase letters, numbers, dashes, underscores, '
                    .'slashes, and {param} placeholders - for example products/{id}.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'path.unique' => 'An active endpoint already uses this method and path.',
        ];
    }
}
