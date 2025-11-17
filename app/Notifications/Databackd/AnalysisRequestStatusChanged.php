<?php

namespace App\Notifications\Databackd;

use App\Models\Databackd\AnalysisRequest;

/**
 * Sent to the client when the status of an analysis request changes.
 */
class AnalysisRequestStatusChanged extends AnalysisRequestNotification
{

    public function __construct(AnalysisRequest $analysis, protected ?string $previousStatus = null)
    {
        parent::__construct($analysis);
    }

    protected function kind(): string
    {
        return 'status_changed';
    }

    protected function texts($notifiable): array
    {
        $status = __('databackd-teams-common.analysis_status.' . $this->analysis->status);

        $subject = __('databackd-teams-analysis.notify.status_subject', ['title' => $this->analysis->title, 'status' => $status]);
        // $subject holds the title (user input) and $content is inserted as HTML into the mail
        $content = e($subject) . '<br>' .
            __('databackd-teams-analysis.notify.line_title') . ': ' . e($this->analysis->title) . '<br>' .
            __('databackd-teams-analysis.notify.line_status') . ': ' . $status;

        return [$subject, $content];
    }

}
