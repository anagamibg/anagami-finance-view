{{-- AFV (S5): one line of the notification bell for the Teams service (payload: message, title, url) --}}
<x-cards.notification :notification="$notification" :link="$notification->data['url'] ?? 'javascript:;'"
                      :image="company()->logo_url"
                      :title="$notification->data['message'] ?? ''" :text="e($notification->data['title'] ?? '')"
                      :time="$notification->created_at"/>
