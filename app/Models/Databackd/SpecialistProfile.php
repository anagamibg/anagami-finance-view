<?php

namespace App\Models\Databackd;

use App\Models\BaseModel;
use App\Models\User;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service profile of an employee who can be assigned to client teams.
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $role financial_analyst|accountant|controller|business_analyst|data_analyst|team_lead
 * @property string $seniority junior|mid|senior
 * @property float $hourly_rate EUR
 * @property int $weekly_capacity_hours
 * @property string|null $languages
 * @property array|null $skills
 * @property bool $is_available
 */
class SpecialistProfile extends BaseModel
{

    use HasCompany;

    const ROLES = ['financial_analyst', 'accountant', 'controller', 'business_analyst', 'data_analyst', 'team_lead'];
    const SENIORITIES = ['junior', 'mid', 'senior'];

    protected $table = 'specialist_profiles';
    protected $guarded = ['id'];
    protected $casts = ['skills' => 'array', 'is_available' => 'boolean', 'hourly_rate' => 'float'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeamRequestMember::class, 'specialist_profile_id');
    }

}
