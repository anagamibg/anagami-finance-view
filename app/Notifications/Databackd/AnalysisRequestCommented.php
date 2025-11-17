<?php

namespace App\Notifications\Databackd;

use App\Models\Databackd\AnalysisRequest;
use App\Models\Databackd\AnalysisRequestComment;
use Illuminate\Support\Str;

/**
 * Sent to the other side (client / assignee) when somebody comments an analysis request.
 */
class AnalysisRequestCommented extends AnalysisRequestNotification
{

    public function __construct(AnalysisRequest $analysis, protected AnalysisRequestComment $comment)
    {
        parent::__construct($analysis);
    }

    protected function kind(): string
    {
        return 'commented';
    }

    protected function texts($notifiable): array
    {
        $author = $this->comment->user?->name ?? '-';

        $subject = __('databackd-teams-analysis.notify.comment_subject', ['author' => $author, 'title' => $this->analysis->title]);
        // $subject holds the author and the title (user input) and $content is inserted as HTML into the mail
        $content = e($subject) . '<br>' . e(Str::limit($this->comment->comment, 300));

        return [$subject, $content];
    }

}
