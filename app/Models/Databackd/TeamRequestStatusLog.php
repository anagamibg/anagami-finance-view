<?php

namespace App\Models\Databackd;

use App\Models\BaseModel;
use App\Models\User;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status history of a team request (append only, no updated_at).
 *
 * @property int $id
 * @property int $company_id
 * @property int $team_request_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $note
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class TeamRequestStatusLog extends BaseModel
{

    use HasCompany;

    const UPDATED_AT = null;

    protected $table = 'team_request_status_logs';
    protected $guarded = ['id'];

    public function teamRequest(): BelongsTo
    {
        return $this->belongsTo(TeamRequest::class, 'team_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
