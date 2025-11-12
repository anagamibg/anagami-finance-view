<?php

namespace App\Models\Databackd;

use App\Models\BaseModel;
use App\Models\User;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comment (with an optional attachment) on an analysis request.
 *
 * @property int $id
 * @property int $company_id
 * @property int $analysis_request_id
 * @property int $user_id
 * @property string $comment
 * @property string|null $file
 */
class AnalysisRequestComment extends BaseModel
{

    use HasCompany;

    protected $table = 'analysis_request_comments';
    protected $guarded = ['id'];

    public function analysisRequest(): BelongsTo
    {
        return $this->belongsTo(AnalysisRequest::class, 'analysis_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
