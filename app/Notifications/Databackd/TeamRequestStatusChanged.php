<?php

namespace App\Notifications\Databackd;

use App\Models\Databackd\TeamRequest;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to the client when an admin changes the status of his team request, and (S12) to the admins when the client
 * accepts / declines the proposal or withdraws the request ($forStaff).
 */
class TeamRequestStatusChanged extends BaseNotification
{

    private TeamRequest $teamRequest;
    private ?string $note;
    private bool $forStaff;

    public function __construct(TeamRequest $teamRequest, ?string $note = null, bool $forStaff = false)
    {
        $this->teamRequest = $teamRequest;
        $this->note = $note;
        $this->forStaff = $forStaff;
        $this->company = $teamRequest->company;
    }

    public function via($notifiable)
    {
        $via = ['database'];

        // AFV (S12): like AnalysisRequestNotification - no mail through an unverified SMTP setup (it only hangs the queue)
        if ($notifiable->email_notifications && $notifiable->email != '' && TeamRequestCreated::smtpUsable()) {
            $via[] = 'mail';
        }

        return $via;
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);

        $url = getDomainSpecificUrl(route('databackd.teams.requests.show', $this->teamRequest->id), $this->company);
        $content = e($this->text());

        if ($this->note) {
            $content .= '<br>' . e(__('databackd-teams-requests.notify.status_note', ['note' => $this->note]));
        }

        $build
            ->subject(__('databackd-teams-requests.notify.status_subject') . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company->header_color,
                'actionText' => __('databackd-teams-requests.notify.status_action'),
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    public function toArray($notifiable)
    {
        return [
            'id' => $this->teamRequest->id,
            'team_request_id' => $this->teamRequest->id,
            'title' => $this->teamRequest->title,
            'status' => $this->teamRequest->status,
            'note' => $this->note,
            'message' => $this->text(),
            'url' => route('databackd.teams.requests.show', $this->teamRequest->id),
        ];
    }

    private function text(): string
    {
        return __($this->forStaff ? 'databackd-teams-requests.notify.client_status_text' : 'databackd-teams-requests.notify.status_text', [
            'client' => $this->teamRequest->client?->name ?? '',
            'title' => $this->teamRequest->title,
            'status' => __('databackd-teams-common.team_status.' . $this->teamRequest->status),
        ]);
    }

}
