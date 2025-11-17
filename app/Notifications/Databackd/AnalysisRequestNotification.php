<?php

namespace App\Notifications\Databackd;

use App\Models\Databackd\AnalysisRequest;
use App\Models\SmtpSetting;
use App\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Shared behaviour of the analysis request notifications: database + mail, texts from resources/lang/*\/databackd-teams-analysis.php.
 */
abstract class AnalysisRequestNotification extends BaseNotification
{

    protected AnalysisRequest $analysis;

    public function __construct(AnalysisRequest $analysis)
    {
        $this->analysis = $analysis;
        $this->company = $analysis->company;
    }

    /**
     * @return array [subject, content (HTML allowed)] in the current locale
     */
    abstract protected function texts($notifiable): array;

    /**
     * Notification kind stored in the database payload (created|status_changed|commented).
     */
    abstract protected function kind(): string;

    public function via($notifiable)
    {
        $via = ['database'];

        if ($this->mailAllowed($notifiable)) {
            $via[] = 'mail';
        }

        return $via;
    }

    /**
     * Mail only to users who want e-mails; with an unverified SMTP setup mails are skipped (they would only hang).
     */
    protected function mailAllowed($notifiable): bool
    {
        if (empty($notifiable->email) || !$notifiable->email_notifications) {
            return false;
        }

        $smtp = SmtpSetting::first();

        return !($smtp && $smtp->mail_driver === 'smtp' && !$smtp->verified);
    }

    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);

        [$subject, $content] = $this->texts($notifiable);

        $url = getDomainSpecificUrl(route('databackd.teams.analysis.show', $this->analysis->id), $this->company);

        $build
            ->subject($subject . ' - ' . config('app.name'))
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'themeColor' => $this->company?->header_color,
                'actionText' => __('databackd-teams-analysis.notify.action'),
                'notifiableName' => $notifiable->name,
            ]);

        parent::resetLocale();

        return $build;
    }

    // phpcs:ignore
    public function toArray($notifiable)
    {
        [$subject] = $this->texts($notifiable);

        return [
            'id' => $this->analysis->id,
            'user_id' => $notifiable->id,
            'kind' => $this->kind(),
            'title' => $this->analysis->title,
            'status' => $this->analysis->status,
            'message' => $subject,
            'url' => route('databackd.teams.analysis.show', $this->analysis->id, false),
        ];
    }

    protected function typeLabel(): string
    {
        return __('databackd-teams-common.analysis_type.' . $this->analysis->type);
    }

}
