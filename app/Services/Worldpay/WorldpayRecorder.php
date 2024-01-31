<?php

namespace App\Services\Worldpay;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RecurringInvoice;
use App\Traits\MakeOrderInvoiceTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class WorldpayRecorder
{
    use MakeOrderInvoiceTrait;

    /**
     * Apply a terminal Worldpay result once. The same transaction reference updates the existing payment.
     *
     * @return array{decision: string, message: string}
     */
    public function settle(string $reference, array $body, ?array $attempt = null): array
    {
        $normalized = WorldpaySupport::normalize($body);
        $parsed = WorldpaySupport::parseReference($reference);
        $attempt = $attempt ?: WorldpayAttempt::find($reference);

        if (!$parsed && !$attempt) {
            return ['decision' => 'ignore', 'message' => 'Unknown Worldpay reference.'];
        }

        $type = $attempt['type'] ?? $parsed['type'];
        $id = (int) ($attempt['id'] ?? $parsed['id']);
        $companyId = isset($attempt['company_id']) ? (int) $attempt['company_id'] : null;

        if ($type === 'subscription') {
            return ['decision' => 'ignore', 'message' => 'Subscription payments are settled separately.'];
        }

        $existing = Payment::where('gateway', 'Worldpay')->where('transaction_id', $reference)->first();
        $decision = WorldpaySupport::recordDecision($existing?->status, $normalized['outcome']);

        if ($decision === 'skip' || $decision === 'ignore') {
            return ['decision' => $decision, 'message' => $normalized['message']];
        }

        if ($decision === 'pending') {
            WorldpayAttempt::markOpen($type, $id, $reference);

            return ['decision' => 'pending', 'message' => __('modules.worldpay.processing')];
        }

        if ($type === 'order') {
            return $this->settleOrder($reference, $id, $companyId, $decision, $normalized, $attempt);
        }

        return $this->settleInvoice($reference, $id, $companyId, $decision, $normalized, $attempt, $existing);
    }

    private function settleInvoice(string $reference, int $id, ?int $companyId, string $decision, array $normalized, ?array $attempt, ?Payment $existing): array
    {
        $invoice = Invoice::with(['currency', 'company'])->find($id);

        if (!$invoice || ($companyId && (int) $invoice->company_id !== $companyId)) {
            return ['decision' => 'ignore', 'message' => 'Invoice not found for this Worldpay payment.'];
        }

        if ($decision === 'complete' && !$existing && !WorldpaySupport::canStartCharge((float) $invoice->amountDue(), $invoice->status) && empty($attempt)) {
            return ['decision' => 'skip', 'message' => __('messages.invoiceAlreadyPaid')];
        }

        $amount = isset($attempt['amount'])
            ? (float) $attempt['amount']
            : WorldpaySupport::majorAmount($normalized['minor'], $normalized['currency'] ?: ($invoice->currency->currency_code ?? 'USD'));

        $status = $decision === 'complete' ? 'complete' : 'failed';
        $existed = (bool) $existing;
        $payment = $this->recordPayment($invoice, $amount, $reference, $status);
        $this->storePaymentId($payment, $normalized['payment_id'] ?? null);

        if ($decision === 'complete' && $existed) {
            $this->markUpdatedPaymentPaid($invoice, $payment);
        }

        if ($decision === 'complete') {
            $this->rememberCard($attempt, $normalized);
            WorldpayAttempt::clearOpen('invoice', $invoice->id);
            WorldpayAttempt::release('invoice', $invoice->id);
        }

        if ($decision === 'failed') {
            WorldpayAttempt::clearOpen('invoice', $invoice->id);
            WorldpayAttempt::release('invoice', $invoice->id);
        }

        Log::info('Worldpay invoice settlement', [
            'reference' => $reference,
            'invoice_id' => $invoice->id,
            'decision' => $decision,
        ]);

        return ['decision' => $decision, 'message' => $normalized['message']];
    }

    private function settleOrder(string $reference, int $id, ?int $companyId, string $decision, array $normalized, ?array $attempt): array
    {
        $order = Order::with(['currency', 'company'])->find($id);

        if (!$order || ($companyId && (int) $order->company_id !== $companyId)) {
            return ['decision' => 'ignore', 'message' => 'Order not found for this Worldpay payment.'];
        }

        $amount = isset($attempt['amount'])
            ? (float) $attempt['amount']
            : WorldpaySupport::majorAmount($normalized['minor'], $normalized['currency'] ?: ($order->currency->currency_code ?? 'USD'));

        $orderStatus = $decision === 'complete' ? 'completed' : 'failed';
        $invoice = $this->makeOrderInvoice($order, $orderStatus);
        $payment = $this->recordPayment($invoice, $amount, $reference, $decision === 'complete' ? 'complete' : 'failed');
        $this->storePaymentId($payment, $normalized['payment_id'] ?? null);

        WorldpayAttempt::clearOpen('order', $order->id);
        WorldpayAttempt::release('order', $order->id);

        return ['decision' => $decision, 'message' => $normalized['message']];
    }

    private function markUpdatedPaymentPaid(Invoice $invoice, Payment $payment): void
    {
        $invoice->refresh();
        $due = (float) $invoice->due_amount - (float) $payment->amount;
        $invoice->status = $due > 0 ? 'partial' : 'paid';
        $invoice->due_amount = $due;
        $invoice->save();
    }

    private function rememberCard(?array $attempt, array $normalized): void
    {
        if (empty($attempt['recurring_invoice_id']) || empty($attempt['store_card'])) {
            return;
        }

        if (empty($normalized['token_href']) || empty($normalized['scheme_reference'])) {
            Log::warning('Worldpay recurring agreement was authorized without a token or scheme reference.', [
                'recurring_invoice_id' => $attempt['recurring_invoice_id'],
            ]);

            return;
        }

        $recurring = RecurringInvoice::find($attempt['recurring_invoice_id']);

        if (!$recurring || (int) $recurring->enable_auto_pay !== 1) {
            return;
        }

        $recurring->worldpay_token = $normalized['token_href'];
        $recurring->worldpay_scheme_reference = $normalized['scheme_reference'];
        $recurring->worldpay_sub_status = 1;
        $recurring->failed_payment_attempts = 0;
        $recurring->saveQuietly();
    }

    private function recordPayment($invoice, float $amount, string $reference, string $status): Payment
    {
        $payment = Payment::query()
            ->where('gateway', 'Worldpay')
            ->where('transaction_id', $reference)
            ->first();

        if (!$payment) {
            $payment = new Payment();
        }

        $payment->project_id = $invoice->project_id;
        $payment->invoice_id = $invoice->id;
        $payment->order_id = $invoice->order_id;
        $payment->gateway = 'Worldpay';
        $payment->transaction_id = $reference;
        $payment->event_id = $reference;
        $payment->currency_id = $invoice->currency_id;
        $payment->amount = $amount;
        $payment->paid_on = now();
        $payment->status = $status;

        try {
            $payment->save();
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = Payment::query()->where('transaction_id', $reference)->where('gateway', 'Worldpay')->first();

            if (!$existing) {
                throw $exception;
            }

            return $existing;
        }

        return $payment;
    }

    private function storePaymentId(Payment $payment, ?string $paymentId): void
    {
        if (!$paymentId || $payment->event_id === $paymentId) {
            return;
        }

        $payment->event_id = $paymentId;
        $payment->saveQuietly();
    }

}
