<?php

namespace App\Http\Requests\Store;

/**
 * The same address rules, with every field optional.
 *
 * It derives from StoreAddressRequest instead of repeating its rule list: the
 * two lists are meant to be identical, and while they were separate copies the
 * create form and the edit form could (and did) accept different values for the
 * same column — the edit copy still allowed a null `postal_code`, which the
 * database rejects. Nothing can pass an update that the create form refuses.
 */
class UpdateAddressRequest extends StoreAddressRequest
{
    public function rules(): array
    {
        $rules = [];

        foreach (parent::rules() as $field => $fieldRules) {
            $rules[$field] = array_values(array_unique(array_merge(['sometimes'], $fieldRules)));
        }

        return $rules;
    }
}
