@php
    use App\Models\User;$notificationUser = User::find($notification->data['id']);
@endphp

@if ($notificationUser)
    <x-cards.notification :notification="$notification" :link="databackd_client_url($notification->data['id']) ?? 'javascript:;'"
                          :image="$notificationUser->image_url"
                          :title="__('email.newCustomer.subject')" :text="$notification->data['name']"
                          :time="$notification->created_at"/>
@endif
