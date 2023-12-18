<?php

namespace App\Http\Requests;

use App\Models\Proposal;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Http\FormRequest;

class ProposalAcceptRequest extends FormRequest
{

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
        $rules = [];

        if (request('type') == 'accept') {

            $rules['full_name'] = 'required';
            $rules['email'] = 'required|email:rfc,strict';

            // Public accept is authorized by the proposal hash in the URL.
            // A posted id is optional, and CompanyScope must not hide the row
            // when the signer is logged into another company.
            $proposal = Proposal::withoutGlobalScope(CompanyScope::class)
                ->where('hash', (string) $this->route('hash'))
                ->first();

            if ($proposal && $proposal->signature_approval == 1) {
                if(request('signature_type') == 'upload'){
                    $rules['image'] = 'required';
                }
                else {
                    $rules['signature'] = 'required';
                }
            }

        }

        return $rules;
    }

}
