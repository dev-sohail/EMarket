<?php

namespace App\Http\Requests\Admin\ApiManagement;

use App\Models\ApiEndpoint;
use Illuminate\Validation\Rule;

class UpdateApiEndpointRequest extends StoreApiEndpointRequest
{
    public function rules(): array
    {
        $currentEndpoint = $this->route('apiEndpoint');

        $rules = parent::rules();

        $rules['path'] = [
            'required',
            'string',
            'max:500',
            $this->pathRule(),
            Rule::unique('api_endpoints', 'path')
                ->where(fn ($query) => $query
                    ->where('method', $this->input('method'))
                    ->whereNull('deleted_at'))
                // The table passed to ignore() must be the one being validated.
                // Passing api_definitions here made the generated SQL compare
                // `api_endpoints` <> <id> as if it were a column, so every
                // endpoint update died with "Unknown column 'api_endpoints'".
                ->ignore(
                    $currentEndpoint instanceof ApiEndpoint ? $currentEndpoint->getKey() : null,
                    '_id'
                ),
        ];

        return $rules;
    }
}
