<?php

namespace App\Http\Requests\CreditNotes;

use App\Http\Requests\CoreRequest;
use App\Traits\CustomFieldsRequestTrait;

class UpdateCreditNote extends CoreRequest
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
            'issue_date' => 'required',
            'sub_total' => 'required',
            'total' => 'required'
        ];

        if($this->recurring_payment == 'yes')
        {
            $rules['billing_frequency'] = 'required';
            $rules['billing_interval'] = 'required|integer';
            $rules['billing_cycle'] = 'required|integer';
        }

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
