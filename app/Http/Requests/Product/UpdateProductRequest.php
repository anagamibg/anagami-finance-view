<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\CoreRequest;
use App\Traits\CustomFieldsRequestTrait;

class UpdateProductRequest extends CoreRequest
{
    use CustomFieldsRequestTrait;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'name' => 'required',
            'price' => 'required|numeric',
            'cost_price' => 'nullable|numeric|min:0|max:9999999999999', // AFV (S8)
            'downloadable_file' => 'nullable|file',
        ];

        $rules = $this->customFieldRules($rules);

        return $rules;
    }

    public function attributes()
    {
        $attributes = [
            'cost_price' => __('databackd-gaps-margins.cost_price.label'), // AFV (S8)
        ];

        $attributes = $this->customFieldsAttributes($attributes);

        return $attributes;
    }

}
