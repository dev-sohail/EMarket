<?php

namespace App\Http\Requests\Ajax;

use Illuminate\Foundation\Http\FormRequest;

class AjaxReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,_id'],
            'return_reason_id' => ['required', 'exists:return_reasons,_id'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'exists:order_items,_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            // The form only collects one reason for the whole return, but
            // ReturnService stores it per item, so the value is copied down to
            // each line here.
            'items.*.return_reason_id' => ['nullable', 'exists:return_reasons,_id'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('items')) {
            $reasonId = $this->input('return_reason_id');

            foreach ($this->input('items', []) as $index => $item) {
                if (empty($item['return_reason_id']) && $reasonId !== null) {
                    $this->merge(["items.{$index}.return_reason_id" => $reasonId]);
                }
            }
        }
    }
}
