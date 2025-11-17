<?php

namespace App\Notifications\Databackd;

use App\Models\Databackd\AnalysisRequest;

/**
 * Sent to the company admins when a new analysis request is created.
 */
class AnalysisRequestCreated extends AnalysisRequestNotification
{

    protected function kind(): string
    {
        return 'created';
    }

    protected function texts($notifiable): array
    {
        $client = $this->analysis->client?->name ?? '-';

        $subject = __('databackd-teams-analysis.notify.created_subject', ['client' => $client]);
        // $subject holds the client name (user input) and $content is inserted as HTML into the mail
        $content = e($subject) . '<br>' .
            __('databackd-teams-analysis.notify.line_title') . ': ' . e($this->analysis->title) . '<br>' .
            __('databackd-teams-analysis.notify.line_type') . ': ' . $this->typeLabel() . '<br>' .
            __('databackd-teams-analysis.notify.line_due') . ': ' . ($this->analysis->due_date?->format($this->company?->date_format ?? 'd.m.Y') ?? '-');

        return [$subject, $content];
    }

}
