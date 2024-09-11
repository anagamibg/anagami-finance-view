<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Company;
use App\Models\SuperAdmin\GlobalInvoice;
use App\Models\SuperAdmin\GlobalSubscription;
use App\Notifications\SuperAdmin\CompanyRenewedPlan;
use App\Models\User;
use App\Services\SuperAdmin\GatewaySubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;

class PaypalIPNController extends Controller
{

    // phpcs:ignore
    public function verifyBillingIPN(Request $request)
    {

        $txnType = $request->get('txn_type');

        if ($txnType == 'recurring_payment') {

            $recurringPaymentId = $request->get('recurring_payment_id');
            $eventId = $request->get('ipn_track_id');

            $event = GlobalInvoice::where('gateway_name', 'paypal')->where('event_id', $eventId)->count();

            if ($event == 0) {
                $payment = GlobalInvoice::where('gateway_name', 'paypal')->where('transaction_id', $recurringPaymentId)->first();
                $today = now();

                if ($payment->package_type == 'annual') {
                    $nextPaymentDate = $today->addYear();
                } else if (company()->package_type == 'monthly') {
                    $nextPaymentDate = $today->addMonth();
                }

                $paypalInvoice = new GlobalInvoice();
                $paypalInvoice->transaction_id = $recurringPaymentId;
                $paypalInvoice->company_id = $payment->company_id;
                $paypalInvoice->currency_id = $payment->currency_id;
                $paypalInvoice->total = $payment->total;
                $paypalInvoice->status = 'active';
                $paypalInvoice->plan_id = $payment->plan_id;
                $paypalInvoice->billing_frequency = $payment->billing_frequency;
                $paypalInvoice->event_id = $eventId;
                $paypalInvoice->billing_interval = 1;
                $paypalInvoice->paid_on = $today;
                $paypalInvoice->next_pay_date = $nextPaymentDate;
                $paypalInvoice->global_subscription_id = $payment->global_subscription_id;
                $paypalInvoice->save();

                $company = Company::findOrFail($payment->company_id);
                $subscription = GlobalSubscription::find($payment->global_subscription_id);
                $gateways = app(GatewaySubscriptionService::class);

                if ($subscription && $gateways->shouldStopRecurring($subscription)) {
                    $gateways->cancel($subscription);

                    return response('IPN Handled', 200);
                }

                // Change company status active after payment
                $company->status = 'active';
                $company->save();

                // recurring_payment IPN is always a subscription renewal, not a new purchase
                $generatedBy = User::whereNull('company_id')->get();
                $packageId = $payment->package_id ?? $payment->plan_id;
                Notification::send($generatedBy, new CompanyRenewedPlan($company, $packageId));

                return response('IPN Handled', 200);
            }
        }
    }
}
