<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Scopes\ActiveScope;
use App\Scopes\CompanyScope;
use Illuminate\Foundation\Http\FormRequest;

class AccountSetupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return static::isSetupAllowed();
    }

    /**
     * Allow initial account setup only when no privileged account exists yet.
     */
    public static function isSetupAllowed(): bool
    {
        $users = User::withoutGlobalScopes([ActiveScope::class, CompanyScope::class]);

        if (isWorksuiteSaas()) {
            return !$users->where('is_superadmin', 1)->exists();
        }

        return !$users->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $global = global_setting();

        $rules = [
            'company_name' => 'required',
            'full_name' => 'required',
            'email' => 'required|email:rfc,strict',
            'password' => 'required|min:8',
        ];

        if ($global && $global->sign_up_terms == 'yes') {
            $rules['terms_and_conditions'] = 'required';
        }

        return $rules;
    }

}
