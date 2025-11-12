<?php

namespace App\Models\Databackd;

use App\Helper\Files;
use App\Models\BaseModel;
use App\Models\User;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Monthly task from a client to the team: report, analysis, ratio review, budget, forecast...
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $team_request_id
 * @property int $client_id users.id of the client
 * @property string $type report|analysis|ratio_review|budget|forecast|consolidation|other
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $period_from
 * @property \Illuminate\Support\Carbon|null $period_to
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string $priority low|medium|high
 * @property string $recurrence none|monthly|quarterly
 * @property int|null $parent_id previous instance of a recurring request
 * @property string $status new|in_progress|review|delivered|closed
 * @property int|null $assigned_to users.id of the specialist
 * @property string|null $deliverable_file stored file name (folder databackd/analysis)
 * @property string|null $deliverable_link link to a saved report / forecast
 * @property int|null $client_rating 1-5
 * @property string|null $client_feedback
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property int|null $created_by
 */
class AnalysisRequest extends BaseModel
{

    use HasCompany;

    const TYPES = ['report', 'analysis', 'ratio_review', 'budget', 'forecast', 'consolidation', 'other'];
    const STATUSES = ['new', 'in_progress', 'review', 'delivered', 'closed'];
    const PRIORITIES = ['low', 'medium', 'high'];
    const RECURRENCES = ['none', 'monthly', 'quarterly'];

    /** Folder of uploaded deliverables and comment attachments (relative path inside PRIVATE_DISK and, for legacy files, inside the public "local" disk). */
    const FILE_FOLDER = 'databackd/analysis';

    /**
     * S6/P4: disk new uploads are written to - storage/app/databackd/ (see config/filesystems.php), never the
     * public/user-uploads tree, so a file can no longer be fetched by a direct, guessable URL that skips every
     * access check in AnalysisRequestController / AnalysisRequestCommentController. Only download()/resolveDownload()
     * below ever read from it, and only after the controller has already checked the requester owns the request.
     *
     * Files written before this fix (incl. demo seed data) are still on the old public "local" disk under
     * FILE_FOLDER; resolveDownload() falls back to that path (via the shared download_local_s3() helper) when a
     * name is not found on PRIVATE_DISK, so existing deliverable_file / comment file values keep working. See
     * docs/mvp/security/findings/P4.md ("За S10") for moving those existing files on production.
     */
    const PRIVATE_DISK = 'databackd_private';

    /**
     * Extensions blocked here on top of Files::validateUploadedFile()'s own deny-list (S6/P4): that list already
     * covers php (and php3-5/phtml), phar, exe, js, etc, but not svg or html/htm, which can carry a script tag
     * that a browser would execute if the file were ever opened inline rather than downloaded.
     */
    const EXTRA_FORBIDDEN_EXTENSIONS = ['svg', 'svgz', 'html', 'htm', 'xhtml', 'shtml'];

    protected $table = 'analysis_requests';
    protected $guarded = ['id'];
    protected $casts = [
        'period_from' => 'date:Y-m-d',
        'period_to' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'delivered_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function teamRequest(): BelongsTo
    {
        return $this->belongsTo(TeamRequest::class, 'team_request_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnalysisRequestComment::class, 'analysis_request_id')->orderBy('created_at');
    }

    // ------------------------------------------------------------------ files (S6/P4 - see PRIVATE_DISK above)

    /**
     * Validate and store a deliverable / comment attachment on PRIVATE_DISK under FILE_FOLDER.
     *
     * @return string the generated, random stored file name (never the client's original name)
     * @throws \Exception on a disallowed extension/mime, an oversized file or a storage-quota violation
     *                     (same rules Files::uploadLocalOrS3() enforces elsewhere in the app)
     */
    public static function storeUpload(UploadedFile $file): string
    {
        $extension = strtolower((string)$file->getClientOriginalExtension());

        if ($extension !== '' && in_array($extension, self::EXTRA_FORBIDDEN_EXTENSIONS, true)) {
            throw new \Exception('You are not allowed to upload files with extension: ' . $extension);
        }

        // Reuses the shared deny-list (php/phtml/phar/exe/js/...), double-extension, mime-type, min-size and
        // per-company storage-quota checks - the same validation every other upload in the app goes through.
        Files::validateUploadedFile($file);

        $name = (string)Str::uuid() . ($extension !== '' ? '.' . $extension : '');

        Storage::disk(self::PRIVATE_DISK)->putFileAs(self::FILE_FOLDER, $file, $name);

        return $name;
    }

    /**
     * Stream $filename (a deliverable_file / comment file value) to the browser as $downloadName, forcing a
     * download (never an inline render) regardless of the file's own content. The caller must already have
     * checked the requester is allowed to see the owning AnalysisRequest/comment before calling this.
     *
     * Looks on PRIVATE_DISK first (every file stored by storeUpload() above); falls back to the legacy public
     * "local" disk path (download_local_s3(), app/Helper/start.php) for files written before this fix.
     */
    public static function resolveDownload(string $filename, string $downloadName): Response
    {
        $disk = Storage::disk(self::PRIVATE_DISK);
        $relative = self::FILE_FOLDER . '/' . $filename;

        if ($disk->exists($relative)) {
            return $disk->download($relative, $downloadName, [
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Legacy path (files written before this fix, incl. demo seed data): still under public/user-uploads/...
        $legacy = (object)['filename' => $filename, 'name' => pathinfo($downloadName, PATHINFO_FILENAME)];
        $response = download_local_s3($legacy, self::FILE_FOLDER . '/' . $filename);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * Delete $filename from PRIVATE_DISK (new uploads) and, best-effort, from the legacy public disk too
     * (Files::deleteFile() is a no-op when the name is not found there - safe to always call).
     */
    public static function deleteUpload(string $filename): void
    {
        Storage::disk(self::PRIVATE_DISK)->delete(self::FILE_FOLDER . '/' . $filename);
        Files::deleteFile($filename, self::FILE_FOLDER);
    }

}
