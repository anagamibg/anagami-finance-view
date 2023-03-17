<?php

namespace App\Http\Requests\Proposal;

use App\Http\Requests\CoreRequest;
use App\Traits\CustomFieldsRequestTrait;

class StoreRequest extends CoreRequest
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
        $setting = company();
        $today = now()->format($setting->date_format);

        $rules = [
            'valid_till' => 'required|date_format:"' . $setting->date_format . '"|after_or_equal:' . $today,
            'sub_total' => 'required',
            'total' => 'required',
            'deal_id' => 'required'
        ];

        $rules = $this->customFieldRules($rules);

        return $rules;
    }

    public function attributes()
    {
        $attributes = [];

        $attributes = $this->customFieldsAttributes($attributes);

        return $attributes;
    }

}
