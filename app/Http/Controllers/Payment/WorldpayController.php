<?php

namespace App\Http\Controllers\Payment;

use App\Helper\Reply;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentGatewayCredentials;
use App\Models\RecurringInvoice;
use App\Services\Worldpay\WorldpayAttempt;
use App\Services\Worldpay\WorldpayClient;
use App\Services\Worldpay\WorldpayRecorder;
use App\Services\Worldpay\WorldpaySupport;
use App\Traits\MakePaymentTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WorldpayController extends Controller
{

    use MakePaymentTrait;

    public function paymentWithWorldpayPublic(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:invoice,order',
            'session_href' => 'required|string|max:2048',
            'device_data' => 'nullable|array',
        ]);

        $target = $this->target((string) $request->type, (int) $id);

        if (isset($target['error'])) {
            return $target['error'];
        }

        if (WorldpayAttempt::openReference($target['type'], $target['id'])) {
            return Reply::error(__('modules.worldpay.processing'));
        }

        $reference = WorldpaySupport::reference($target['type'], $target['id']);

        if (!WorldpayAttempt::acquire($target['type'], $target['id'], $reference)) {
            return Reply::error(__('modules.worldpay.processing'));
        }

        if ($target['store_card'] && $request->card_agreement != '1') {
            WorldpayAttempt::release($target['type'], $target['id']);

            return Reply::error(__('modules.worldpay.agreementRequired'));
        }

        $attempt = [
            'type' => $target['type'],
            'id' => $target['id'],
            'company_id' => $target['company_id'],
            'amount' => $target['amount'],
            'currency' => $target['currency'],
            'recurring_invoice_id' => $target['recurring_invoice_id'],
            'store_card' => $target['store_card'],
        ];

        WorldpayAttempt::store($reference, $attempt);

        try {
            $client = WorldpayClient::fromCredentials($target['credentials']);
            $body = $client->createCardPayment(
                $reference,
                $target['currency'],
                WorldpaySupport::minorAmount($target['amount'], $target['currency']),
                (string) $request->session_href,
                WorldpaySupport::deviceData((array) $request->input('device_data', [])),
                route('worldpay.3ds_return', ['reference' => $reference]),
                $target['narrative'],
                $target['store_card']
            );
        } catch (RuntimeException $exception) {
            WorldpayAttempt::release($target['type'], $target['id']);
            Log::warning('Worldpay payment could not be started.', ['reference' => $reference, 'message' => $exception->getMessage()]);

            return Reply::error($exception->getMessage());
        }

        return $this->respond($reference, $body, $attempt);
    }

    public function supplyDeviceData(Request $request, $id)
    {
        $request->validate([
            'reference' => 'required|string|max:64',
            'collection_reference' => 'required|string|max:128',
        ]);

        return $this->continueAction(
            (string) $request->reference,
            (int) $id,
            'supply_href',
            ['collectionReference' => (string) $request->collection_reference]
        );
    }

    public function completeChallenge(Request $request, $id)
    {
        $request->validate([
            'reference' => 'required|string|max:64',
        ]);

        return $this->continueAction((string) $request->reference, (int) $id, 'complete_href', []);
    }

    public function threeDsReturn(Request $request)
    {
        return response()->view('public-payment.worldpay.three-ds-return', [
            'reference' => (string) $request->query('reference', ''),
        ]);
    }

    public function handleGatewayWebhook(Request $request, $companyHash)
    {
        $company = Company::where('hash', $companyHash)->first();

        if (!$company) {
            return response('Unknown company', 404);
        }

        $credentials = PaymentGatewayCredentials::where('company_id', $company->id)->first();
        $secret = (string) ($credentials->worldpay_webhook_secret ?? '');

        if (!WorldpaySupport::signatureMatches($request->getContent(), $request->header('Event-Signature'), $secret)) {
            return response('Invalid signature', 400);
        }

        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return response('Invalid payload', 400);
        }

        $details = $payload['eventDetails'] ?? [];
        $reference = (string) ($details['transactionReference'] ?? '');
        $parsed = WorldpaySupport::parseReference($reference);

        if (!$parsed || !in_array($parsed['type'], ['invoice', 'order'], true)) {
            return response('Ignored', 200);
        }

        $ownerId = $parsed['type'] === 'order'
            ? optional(Order::find($parsed['id']))->company_id
            : optional(Invoice::find($parsed['id']))->company_id;

        if ((int) $ownerId !== (int) $company->id) {
            return response('Ignored', 200);
        }

        $attempt = WorldpayAttempt::find($reference) ?: [
            'type' => $parsed['type'],
            'id' => $parsed['id'],
            'company_id' => $company->id,
        ];

        app(WorldpayRecorder::class)->settle($reference, $payload, $attempt);

        return response('OK', 200);
    }

    private function continueAction(string $reference, int $routeId, string $hrefKey, array $body)
    {
        $attempt = WorldpayAttempt::find($reference);

        if (!$attempt || (int) $attempt['id'] !== $routeId) {
            return Reply::error(__('modules.worldpay.sessionExpired'));
        }

        $href = $attempt[$hrefKey] ?? null;
        $credentials = PaymentGatewayCredentials::where('company_id', $attempt['company_id'])->first();

        if (!$credentials || !$href) {
            return Reply::error(__('modules.worldpay.sessionExpired'));
        }

        try {
            $response = WorldpayClient::fromCredentials($credentials)->postAction($href, $body);
        } catch (RuntimeException $exception) {
            return Reply::error($exception->getMessage());
        }

        return $this->respond($reference, $response, $attempt);
    }

    private function respond(string $reference, array $body, array $attempt)
    {
        $normalized = WorldpaySupport::normalize($body);

        if (WorldpaySupport::isDeviceData($normalized['outcome'])) {
            $href = $body['_actions']['supply3dsDeviceData']['href'] ?? null;

            if (!WorldpaySupport::allowedActionUrl($href)) {
                WorldpayAttempt::release($attempt['type'], $attempt['id']);

                return Reply::error(__('modules.worldpay.errorMessage'));
            }

            WorldpayAttempt::merge($reference, ['supply_href' => $href]);

            return [
                'status' => 'action',
                'action' => 'device_data',
                'reference' => $reference,
                'deviceDataCollection' => [
                    'jwt' => $body['deviceDataCollection']['jwt'] ?? '',
                    'url' => $body['deviceDataCollection']['url'] ?? '',
                    'bin' => $body['deviceDataCollection']['bin'] ?? '',
                ],
            ];
        }

        if (WorldpaySupport::isChallenge($normalized['outcome'])) {
            $href = $body['_actions']['complete3dsChallenge']['href'] ?? null;

            if (!WorldpaySupport::allowedActionUrl($href)) {
                WorldpayAttempt::release($attempt['type'], $attempt['id']);

                return Reply::error(__('modules.worldpay.errorMessage'));
            }

            WorldpayAttempt::merge($reference, ['complete_href' => $href]);

            return [
                'status' => 'action',
                'action' => 'challenge',
                'reference' => $reference,
                'challenge' => [
                    'jwt' => $body['challenge']['jwt'] ?? '',
                    'url' => $body['challenge']['url'] ?? '',
                ],
            ];
        }

        $settlement = app(WorldpayRecorder::class)->settle($reference, $body, $attempt);

        if ($settlement['decision'] === 'complete' || $settlement['decision'] === 'skip') {
            return Reply::success(__('messages.paymentSuccessful'));
        }

        if ($settlement['decision'] === 'pending') {
            return Reply::success(__('modules.worldpay.processing'));
        }

        // AFV (upgrade 6.0.19): an "ignore" result (e.g. a Worldpay error body for a junk session_href) left the
        // 15-minute payment lock in place, so anyone could block the Worldpay payment of any invoice/order by its id
        WorldpayAttempt::release($attempt['type'], $attempt['id']);

        return Reply::error($this->payerMessage($settlement, $normalized));
    }

    /**
     * AFV (upgrade 6.0.19): the payer sees a translated message (S7); only Worldpay's own refusal text is passed
     * through - raw outcome codes ("refused", "fraudHighRisk" …) and the internal English texts of WorldpayRecorder are not.
     */
    private function payerMessage(array $settlement, array $normalized): string
    {
        $message = (string) ($settlement['message'] ?? '');

        if ($settlement['decision'] !== 'failed' || $message === '' || $message === $normalized['outcome']
            || $message === WorldpaySupport::messageFrom([])) {
            return __('modules.worldpay.errorMessage');
        }

        return $message;
    }

    private function target(string $type, int $id): array
    {
        if ($type === 'order') {
            $order = Order::with(['currency', 'company'])->findOrFail($id);

            if (in_array($order->status, ['completed', 'refunded'], true)) {
                return ['error' => Reply::error(__('messages.invoiceAlreadyPaid'))];
            }

            $credentials = $this->activeCredentials((int) $order->company_id);

            if (isset($credentials['error'])) {
                return $credentials;
            }

            return [
                'type' => 'order',
                'id' => $order->id,
                'company_id' => (int) $order->company_id,
                'amount' => (float) $order->total,
                'currency' => strtoupper((string) (optional($order->currency)->currency_code ?: 'USD')),
                'narrative' => (string) ($order->company->company_name ?? config('app.name')),
                'recurring_invoice_id' => null,
                'store_card' => false,
                'credentials' => $credentials['credentials'],
            ];
        }

        $invoice = Invoice::with(['currency', 'company'])->findOrFail($id);

        if (!WorldpaySupport::canStartCharge((float) $invoice->amountDue(), $invoice->status)) {
            return ['error' => Reply::error(__('messages.invoiceAlreadyPaid'))];
        }

        $credentials = $this->activeCredentials((int) $invoice->company_id);

        if (isset($credentials['error'])) {
            return $credentials;
        }

        $recurring = $invoice->invoice_recurring_id
            ? RecurringInvoice::find($invoice->invoice_recurring_id)
            : null;
        $storeCard = $recurring
            && (int) $recurring->enable_auto_pay === 1
            && $credentials['credentials']->worldpay_status === 'active';

        return [
            'type' => 'invoice',
            'id' => $invoice->id,
            'company_id' => (int) $invoice->company_id,
            'amount' => (float) $invoice->amountDue(),
            'currency' => strtoupper((string) (optional($invoice->currency)->currency_code ?: 'USD')),
            'narrative' => (string) ($invoice->company->company_name ?? config('app.name')),
            'recurring_invoice_id' => $storeCard ? $recurring->id : null,
            'store_card' => (bool) $storeCard,
            'credentials' => $credentials['credentials'],
        ];
    }

    private function activeCredentials(int $companyId): array
    {
        $credentials = PaymentGatewayCredentials::where('company_id', $companyId)->first();

        if (!$credentials || $credentials->worldpay_status !== 'active') {
            return ['error' => Reply::error(__('modules.worldpay.unavailable'))];
        }

        return ['credentials' => $credentials];
    }

}
