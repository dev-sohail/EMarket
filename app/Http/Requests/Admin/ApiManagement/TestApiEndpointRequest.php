<?php

namespace App\Http\Requests\Admin\ApiManagement;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the admin-entered inputs for one endpoint test run.
 *
 * Only the shape of the submission is checked here — the endpoint's own
 * declared parameters and rules are enforced by ApiDynamicController at call
 * time, which is precisely what the test is meant to exercise. Validating them
 * again in this request would make an intentionally invalid test impossible to
 * run, because the form post would be rejected before the call ever happened.
 */
class TestApiEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'path' => ['sometimes', 'array'],
            'path.*' => ['nullable', 'string', 'max:255'],
            'query' => ['nullable', 'string', 'max:20000'],
            'headers' => ['nullable', 'string', 'max:5000'],
            'body' => ['nullable', 'string', 'max:65536'],
            'token' => ['nullable', 'string', 'max:512'],
        ];
    }
}
