<?php

namespace App\Http\Controllers\Databackd\Teams;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use App\Http\Requests\Databackd\Teams\AnalysisRequest\CommentAnalysisRequest;
use App\Models\Databackd\AnalysisRequest;
use App\Models\Databackd\AnalysisRequestComment;
use App\Notifications\Databackd\AnalysisRequestCommented;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Comments (with an optional attachment) on an analysis request. Clients only on their own requests.
 */
class AnalysisRequestCommentController extends AccountBaseController
{

    public function store(CommentAnalysisRequest $request, $id)
    {
        $analysis = $this->findRequest($id);

        $comment = new AnalysisRequestComment();
        $comment->company_id = company()->id;
        $comment->analysis_request_id = $analysis->id;
        $comment->user_id = user()->id;
        $comment->comment = trim($request->comment);

        if ($request->hasFile('file')) {
            try {
                // S6/P4: stored on the private disk (AnalysisRequest::PRIVATE_DISK), not public/user-uploads -
                // see AnalysisRequest::storeUpload() for why.
                $comment->file = AnalysisRequest::storeUpload($request->file('file'));
            } catch (\Throwable $e) {
                return Reply::error(e(Str::limit($e->getMessage(), 200))); // AFV (S10): escaped - the toastr renders HTML
            }
        }

        $comment->save();

        $this->notifyOtherSide($analysis, $comment);

        return Reply::successWithData(__('databackd-teams-analysis.messages.commented'), [
            'redirectUrl' => route('databackd.teams.analysis.show', $analysis->id) . '#comments',
        ]);
    }

    public function download($id)
    {
        $comment = AnalysisRequestComment::findOrFail($id);

        // same visibility rule as the request itself (404 for other clients)
        $this->findRequest($comment->analysis_request_id);

        abort_if(!$comment->file, 404);

        // S6/P4: ownership was already checked above (findRequest() 404s for another client); resolveDownload()
        // only now reads the file, from the private disk first and the legacy public path as a fallback.
        $ext = pathinfo($comment->file, PATHINFO_EXTENSION);
        $downloadName = 'attachment-' . $comment->id . ($ext !== '' ? '.' . $ext : '');

        return AnalysisRequest::resolveDownload($comment->file, $downloadName);
    }

    private function findRequest($id): AnalysisRequest
    {
        $query = AnalysisRequest::query();

        if (in_array('client', user_roles())) {
            $query->where('client_id', user()->id);
        }

        return $query->findOrFail($id);
    }

    /**
     * A client comment goes to the assignee (or the admins when nobody is assigned), a staff comment to the client.
     */
    private function notifyOtherSide(AnalysisRequest $analysis, AnalysisRequestComment $comment): void
    {
        try {
            if (in_array('client', user_roles())) {
                $recipients = $analysis->assignee
                    ? collect([$analysis->assignee])
                    : \App\Models\User::allAdmins(company()->id);
            }
            else {
                $recipients = collect([$analysis->client]);
            }

            $recipients = $recipients->filter()->reject(fn($u) => $u->id === user()->id);
        } catch (\Throwable $e) {
            Log::warning('Analysis request comment recipients failed: ' . $e->getMessage());

            return;
        }

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new AnalysisRequestCommented($analysis, $comment));
            } catch (\Throwable $e) {
                Log::warning('Analysis request comment notification failed: ' . $e->getMessage());
            }
        }
    }

}
