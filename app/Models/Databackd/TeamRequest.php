<?php

namespace App\Models\Databackd;

use App\Models\BaseModel;
use App\Models\Project;
use App\Models\User;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Client order for a team of specialists (1, 2, 5, 10, 20, 50+ people, or a few hours per week).
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_id users.id of the client
 * @property int|null $project_id
 * @property string $title
 * @property string $team_size 1|2|5|10|20|50|custom
 * @property int|null $custom_size
 * @property array|null $roles role => count
 * @property string $engagement hours_per_week|full_time
 * @property int|null $hours_per_week
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property int $duration_months
 * @property string|null $description
 * @property string $status new|reviewing|proposed|active|paused|completed|rejected
 * @property float|null $monthly_price_estimate EUR
 * @property int|null $created_by
 */
class TeamRequest extends BaseModel
{

    use HasCompany;

    const SIZES = ['1', '2', '5', '10', '20', '50', 'custom'];
    const STATUSES = ['new', 'reviewing', 'proposed', 'active', 'paused', 'completed', 'rejected'];
    const ENGAGEMENTS = ['hours_per_week', 'full_time'];

    /**
     * AFV (S12): allowed status changes by the company admin (from => [to, ...]).
     * Completed is final; a rejected request can be taken back into review.
     */
    const TRANSITIONS = [
        'new' => ['reviewing', 'proposed', 'rejected'],
        'reviewing' => ['proposed', 'rejected'],
        'proposed' => ['active', 'reviewing', 'rejected'],
        'active' => ['paused', 'completed'],
        'paused' => ['active', 'completed'],
        'completed' => [],
        'rejected' => ['reviewing'],
    ];

    /**
     * AFV (S12): what the client himself may do with his request - accept or decline the proposal,
     * withdraw a request that has no proposal yet.
     */
    const CLIENT_TRANSITIONS = [
        'new' => ['rejected'],
        'reviewing' => ['rejected'],
        'proposed' => ['active', 'rejected'],
    ];

    /** Statuses in which the monthly price is the one of the proposal, not the automatic estimate. */
    const OFFER_STATUSES = ['proposed', 'active', 'paused', 'completed'];

    protected $table = 'team_requests';
    protected $guarded = ['id'];
    protected $casts = ['roles' => 'array', 'start_date' => 'date:Y-m-d', 'monthly_price_estimate' => 'float'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamRequestMember::class, 'team_request_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TeamRequestStatusLog::class, 'team_request_id')->orderBy('created_at');
    }

    public function analysisRequests(): HasMany
    {
        return $this->hasMany(AnalysisRequest::class, 'team_request_id');
    }

    /** @return string[] statuses the request may move to (admin, or the client himself when $asClient) */
    public function allowedTransitions(bool $asClient = false): array
    {
        $map = $asClient ? self::CLIENT_TRANSITIONS : self::TRANSITIONS;

        return $map[$this->status] ?? [];
    }

    public function canMoveTo(string $status, bool $asClient = false): bool
    {
        return in_array($status, $this->allowedTransitions($asClient), true);
    }

    /** Number of people ordered (custom_size for the "other" option). */
    public function getHeadcountAttribute(): int
    {
        return $this->team_size === 'custom' ? (int)$this->custom_size : (int)$this->team_size;
    }

}
