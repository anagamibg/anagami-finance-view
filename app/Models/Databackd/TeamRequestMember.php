<?php

namespace App\Models\Databackd;

use App\Models\BaseModel;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Specialist assigned to a team request.
 *
 * @property int $id
 * @property int $company_id
 * @property int $team_request_id
 * @property int $specialist_profile_id
 * @property string|null $role
 * @property int $hours_per_week
 */
class TeamRequestMember extends BaseModel
{

    use HasCompany;

    protected $table = 'team_request_members';
    protected $guarded = ['id'];

    public function teamRequest(): BelongsTo
    {
        return $this->belongsTo(TeamRequest::class, 'team_request_id');
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(SpecialistProfile::class, 'specialist_profile_id');
    }

}
