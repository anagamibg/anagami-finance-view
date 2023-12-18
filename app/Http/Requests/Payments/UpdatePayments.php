<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\CoreRequest;
use App\Traits\CustomFieldsRequestTrait;

class UpdatePayments extends CoreRequest
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
            'amount' => 'required|numeric|min:1',
            'paid_on' => 'required',
            'offline_methods' => 'required_if:gateway,==,Offline',
        ];

        if ($this->transaction_id) {
            // It need to be unique for all the company
            $rules['transaction_id'] = 'unique:payments,transaction_id,' . $this->route('payment').',id,company_id,' . company()->id;
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

    public function messages()
    {
        return [
            'invoice_id.required' => 'Select the invoice you want to add payment for.'
        ];
    }

}