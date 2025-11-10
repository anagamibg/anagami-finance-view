<?php

namespace App\Http\Requests\Databackd\Teams\TeamRequest;

use App\Http\Requests\CoreRequest;
use App\Models\Databackd\TeamRequest;
use Illuminate\Validation\Rule;

/**
 * Status change of a team request, optionally with a note.
 * The admin moves the request along TeamRequest::TRANSITIONS (with the monthly price of the proposal);
 * the client may only accept / decline the proposal or withdraw his request (TeamRequest::CLIENT_TRANSITIONS) -
 * both checked in TeamRequestController::changeStatus().
 */
class ChangeStatusRequest extends CoreRequest
{

    public function authorize()
    {
        return in_array('admin', user_roles()) || in_array('client', user_roles());
    }

    public function rules()
    {
        return [
            'status' => ['required', Rule::in(TeamRequest::STATUSES)],
            'note' => ['nullable', 'string', 'max:1000'],
            'seen_version' => ['nullable', 'integer'],
            'offer_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function attributes()
    {
        return __('databackd-teams-requests.attributes');
    }

}
