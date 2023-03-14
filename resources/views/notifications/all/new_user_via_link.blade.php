<x-cards.notification :notification="$notification" :link="databackd_employee_url($notification->data['user_id']) ?? 'javascript:;'"
                      :image="$notification->data['image_url']" :title="$notification->data['name']"
                      :text="__('email.newUserViaLink.subject')"
                      :time="$notification->created_at"/>
