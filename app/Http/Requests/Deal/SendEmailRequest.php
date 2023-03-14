<?php

namespace App\Http\Requests\Deal;

use App\Http\Requests\CoreRequest;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Validation\Rule;

class SendEmailRequest extends CoreRequest
{
    // AFV (upgrade 6.0.19): at most this many addresses in copy / attachments
    const MAX_CC = 10;
    const MAX_FILES = 10;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        // AFV (upgrade 6.0.19): the email goes only to the deal contact (the form is prefilled with it) and the copy only
        // to the contact or to users of the company - not to any address typed in (the company SMTP as an open relay).
        $contactEmail = strtolower(trim((string)optional(optional(Deal::with('contact')->find($this->route('deal')))->contact)->client_email));

        return [
            'to' => [
                'required',
                'email',
                function ($attribute, $value, $fail) use ($contactEmail) {
                    if ($contactEmail === '' || strtolower(trim((string)$value)) !== $contactEmail) {
                        $fail(__('ui-crm.dealEmails.toMustBeContact'));
                    }
                },
            ],
            'cc' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($contactEmail) {
                    if ($value === null || trim($value) === '') {
                        return;
                    }

                    $emails = array_values(array_filter(array_map('trim', explode(',', $value))));

                    foreach ($emails as $email) {
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $fail(__('validation.email', ['attribute' => __('app.cc')]));

                            return;
                        }
                    }

                    if (count($emails) > self::MAX_CC) {
                        $fail(__('ui-crm.dealEmails.ccTooMany', ['max' => self::MAX_CC]));

                        return;
                    }

                    $lower = array_map('strtolower', $emails);
                    $companyUserEmails = User::whereIn('email', $emails)->whereHas('employeeDetail')->pluck('email')->map(fn($e) => strtolower($e))->all();
                    $notAllowed = array_diff($lower, $companyUserEmails, [$contactEmail]);

                    if ($notAllowed) {
                        $fail(__('ui-crm.dealEmails.ccNotAllowed', ['emails' => implode(', ', $notAllowed)]));
                    }
                },
            ],
            'subject' => 'required|string|max:500',
            'body' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (trim_editor($value) == '') {
                        $fail(__('validation.required'));
                    }
                },
            ],
            // AFV (upgrade 6.0.19): template of the current company only
            'deal_email_template_id' => ['nullable', 'integer', Rule::exists('deal_email_templates', 'id')->where('company_id', company()->id)],
            'file' => 'nullable|array|max:' . self::MAX_FILES, // AFV (upgrade 6.0.19): limit the number of attachments
            'file.*' => 'file|max:10240',
        ];
    }
}
