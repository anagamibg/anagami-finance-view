<?php

namespace App\Http\Requests\Databackd\Teams\AnalysisRequest;

use App\Http\Requests\CoreRequest;

/**
 * Result of an analysis request: an uploaded file and / or a link to a saved report (staff only).
 */
class DeliverableAnalysisRequest extends CoreRequest
{

    public function authorize()
    {
        return !in_array('client', user_roles());
    }

    public function rules()
    {
        return [
            'deliverable_file' => ['nullable', 'file', 'max:' . (20 * 1024)],
            'deliverable_link' => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                $value = trim((string)$value);

                if ($value === '') {
                    return;
                }

                // an app path ("/account/...") or a full http(s) URL
                // "/\evil.test" is read by browsers as "//evil.test" (scheme-relative), so a backslash is not allowed in a path
                $isPath = str_starts_with($value, '/') && !str_starts_with($value, '//') && !str_contains($value, '\\');
                $isUrl = filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value);

                if (!$isPath && !$isUrl) {
                    $fail(__('databackd-teams-analysis.validation.link'));
                }
            }],
            'deliverable_route' => ['nullable', 'string', 'max:60'],
            'mark_delivered' => ['nullable', 'boolean'],
        ];
    }

    public function attributes()
    {
        return __('databackd-teams-analysis.attributes');
    }

}
