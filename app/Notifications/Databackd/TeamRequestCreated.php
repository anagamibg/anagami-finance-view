<?php

namespace App\Notifications\Databackd;

use App\Models\Databackd\TeamRequest;
use App\Models\SmtpSetting;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to the company admins when a client (or staff) submits a new team request.
 */
class TeamRequestCreated extends BaseNotification
{

    private TeamRequest $teamRequest;

    public function __construct(TeamRequest $teamRequest)
    {
        $this->teamRequest = $teamRequest;
        $this->company = $teamRequest->company;
    }

    public function via($notifiable)
    {
        $via = ['database'];

        if ($notifiable->email_notifications && $notifiable->email != '' && self::smtpUsable()) {
            $via[] = 'mail';
        }

        return $via;
    }

    /**
     * AFV (S12): false for an SMTP setup that was never verified - the mail would only hang / fail in the queue
     * (same rule as AnalysisRequestNotification::mailAllowed()).
     */
    public static function smtpUsable(): bool
    {
        $smtp = SmtpSetting::first();

        return !($smtp && $smtp->mail_driver === 'smtp' && !$smtp->verified);
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);

        $url = getDomainSpecificUrl(route('databackd.teams.requests.show', $this->teamRequest->id), $this->company);
        $subject = __('databackd-teams-requests.notify.created_subject');

        $build
            ->subject($subject . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => $url,
                'content' => e($this->text()),
                'themeColor' => $this->company->header_color,
                'actionText' => __('databackd-teams-requests.notify.created_action'),
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
            'client_id' => $this->teamRequest->client_id,
            'message' => $this->text(),
            'url' => route('databackd.teams.requests.show', $this->teamRequest->id),
        ];
    }

    private function text(): string
    {
        $request = $this->teamRequest;

        return __('databackd-teams-requests.notify.created_text', [
            'client' => $request->client?->name ?? '',
            'title' => $request->title,
            'size' => trans_choice('databackd-teams-requests.people', $request->headcount, ['count' => $request->headcount]),
            'engagement' => $request->engagement === 'full_time'
                ? __('databackd-teams-requests.full_time')
                : __('databackd-teams-requests.hours_week', ['hours' => (int)$request->hours_per_week]),
        ]);
    }

}
