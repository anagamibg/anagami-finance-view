@php
    use Illuminate\Support\Str;

    $data = $notification->data ?? [];
    $title = __('app.newNotifications');
    $text = $data['heading']
        ?? $data['subject']
        ?? $data['holiday_name']
        ?? $data['project_name']
        ?? (is_array($data['task'] ?? null) ? ($data['task']['heading'] ?? null) : null);

    // AFV (upgrade 6.0.19): translated type name instead of the English Str::headline() (S7), and the payload is
    // escaped — x-cards.notification prints :text raw and imported/API data does not pass the XSS input filter (S6)
    $dbkTypeKey = 'ui-shell.notifications.types.' . Str::snake(class_basename($notification->type));
    $text = is_scalar($text) && (string) $text !== ''
        ? e((string) $text)
        : e(\Illuminate\Support\Facades\Lang::has($dbkTypeKey) ? __($dbkTypeKey) : __('ui-shell.notifications.generic'));
@endphp

<x-cards.notification
    :notification="$notification"
    :link="route('all-notifications')"
    :image="company()->logo_url"
    :title="$title"
    :text="$text"
    :time="$notification->created_at"
/>
