<?php

namespace App\Http\Requests\Admin\ApiManagement;

use App\Services\Api\Reflection\DefinitionReflector;
use App\Services\Api\Reflection\ModelResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * Validates the "generate an API from a model" form.
 *
 * The model is resolved and reflected during validation rather than in the
 * controller, so a bad or unreflectable model is reported as a field error on
 * the form instead of a 500 or a redirect back with a generic message. The
 * preview is cheap and read-only, and reflection failing here is the single most
 * likely thing to go wrong, so it is worth catching at the edge.
 */
class ReflectApiDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'max:255'],
            // Left off by default: generating a publicly reachable URL is a
            // decision, matching the CLI's behaviour.
            'activate' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'model' => 'model',
        ];
    }

    /**
     * Run after the basic shape check so the field errors are the specific ones
     * from resolveClass/reflect rather than a generic "reflection failed".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('model')) {
                return;
            }

            try {
                $class = app(ModelResolver::class)->resolveClass($this->string('model')->toString());

                // Reflect to confirm the model is actually exposable, so an
                // unreflectable one is rejected before anything is written.
                app(DefinitionReflector::class)->preview($class);
            } catch (Throwable $e) {
                $validator->errors()->add('model', $e->getMessage());
            }
        });
    }
}
