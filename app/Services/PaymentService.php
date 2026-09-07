<?php

namespace App\Services;

use App\Enums\PaymentCallbackStatus;
use App\Enums\PaymentTxnStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Shetabit\Multipay\Contracts\ReceiptInterface;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PreviouslyVerifiedException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Payment as PaymentManager;
use Shetabit\Multipay\RedirectionForm;

/**
 * Result of processing a gateway callback. `payment` holds the refreshed
 * payment record; `message` carries a Persian, user-facing description.
 */
final class PaymentResult
{
    public function __construct(
        public readonly PaymentCallbackStatus $status,
        public readonly ?Payment $payment = null,
        public readonly ?string $message = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status->isPaid();
    }
}

/**
 * Gateway-agnostic payment orchestration:
 *
 *   start()  →  create the Payment row, purchase at the gateway, return the
 *               redirection payload (rendered as an auto-submitting form).
 *
 *   verifyCallback()  →  verify the returned transaction strictly on the
 *               backend, persist the result idempotently, and only then
 *               fulfill the order. Duplicate / stale / tampered callbacks
 *               never double-fulfill an order.
 */
final class PaymentService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentManager $paymentManager,
    ) {}

    /**
     * Begin a payment for an order and return the payment record carrying
     * the gateway redirection form under the `redirection_form` attribute.
     */
    public function start(Order $order, ?string $driver = null): Payment
    {
        $driver ??= (string) config('payment.default');

        $invoice = new Invoice;
        $invoice->amount(max(1000, (int) $order->total_price));
        $invoice->detail('orderId', $order->order_number);
        $invoice->detail('description', 'پرداخت سفارش '.$order->order_number);

        /** @var PaymentManager $manager */
        $manager = $this->paymentManager->via($driver)->purchase($invoice);

        $payment = Payment::query()->create([
            'order_id' => $order->getKey(),
            'user_id' => $order->user_id,
            'amount' => $order->total_price,
            'gateway' => $driver,
            'authority' => (string) $invoice->getTransactionId(),
            'status' => PaymentTxnStatus::Pending,
        ]);

        // Expose the redirection form to the controller without persisting it.
        $payment->setAttribute('redirection_form', $manager->pay());

        return $payment;
    }

    /**
     * The gateway redirection form created by the last start() call.
     */
    public function redirectionFormOf(Payment $payment): RedirectionForm
    {
        /** @var RedirectionForm $form */
        $form = $payment->getAttribute('redirection_form');

        return $form;
    }

    /**
     * Verify a gateway callback. Handles: success, user cancellation,
     * gateway failure, duplicate (replay) callbacks and unknown tokens.
     */
    public function verifyCallback(Request $request, ?string $driver = null): PaymentResult
    {
        $driver ??= (string) config('payment.default');

        $payment = $this->locatePayment($request);

        if ($payment === null) {
            return new PaymentResult(PaymentCallbackStatus::Unknown, message: 'تراکنش یافت نشد.');
        }

        // User abandoned the gateway (local driver & most real gateways).
        if ($request->boolean('cancel')) {
            $this->fail($payment, 'پرداخت توسط کاربر لغو شد.');

            return new PaymentResult(PaymentCallbackStatus::Canceled, $payment, 'پرداخت شما لغو شد.');
        }

        // Idempotency: already-verified payments never get re-verified.
        if ($payment->status === PaymentTxnStatus::Success) {
            return new PaymentResult(PaymentCallbackStatus::AlreadyVerified, $payment);
        }

        try {
            $receipt = $this->verifyAtGateway($payment, $driver);
        } catch (PreviouslyVerifiedException $e) {
            // The gateway reports this transaction was already verified once.
            // Treat it as success — the payment row is the single truth.
            $payment = $this->markSuccess($payment, (string) $payment->reference_id);
            $this->orderService->markPaid($payment->order);

            return new PaymentResult(PaymentCallbackStatus::AlreadyVerified, $payment);
        } catch (InvalidPaymentException|PurchaseFailedException $e) {
            $this->fail($payment, $e->getMessage());

            return new PaymentResult(PaymentCallbackStatus::Failed, $payment, $e->getMessage());
        }

        $payment = $this->markSuccess($payment, (string) $receipt->getReferenceId());

        $fulfilled = $this->orderService->markPaid($payment->order);

        return new PaymentResult(
            $fulfilled ? PaymentCallbackStatus::Success : PaymentCallbackStatus::AlreadyVerified,
            $payment,
        );
    }

    /**
     * Find the pending payment row using whatever token the gateway sent
     * back (local: transactionId, zibal: trackId, zarinpal: Authority).
     */
    private function locatePayment(Request $request): ?Payment
    {
        $token = (string) ($request->input('transactionId')
            ?? $request->input('trackId')
            ?? $request->input('Authority')
            ?? $request->input('authority')
            ?? $request->input('token')
            ?? '');

        if ($token === '') {
            return null;
        }

        return Payment::query()
            ->where('authority', $token)
            ->with('order')
            ->first();
    }

    /**
     * Re-verify the transaction at the gateway with the stored amount.
     */
    private function verifyAtGateway(Payment $payment, string $driver): ReceiptInterface
    {
        /** @var ReceiptInterface $receipt */
        $receipt = $this->paymentManager
            ->via($driver)
            ->transactionId((string) $payment->authority)
            ->amount(max(1000, (int) $payment->amount))
            ->verify();

        return $receipt;
    }

    /**
     * Persist a successful verification on the payment row.
     */
    private function markSuccess(Payment $payment, string $referenceId): Payment
    {
        $payment->forceFill([
            'status' => PaymentTxnStatus::Success,
            'reference_id' => $referenceId,
            'paid_at' => now(),
        ])->save();

        return $payment;
    }

    /**
     * Persist a failed verification on the payment row.
     */
    private function fail(Payment $payment, string $message): void
    {
        if ($payment->status === PaymentTxnStatus::Success) {
            return;
        }

        $payment->forceFill([
            'status' => PaymentTxnStatus::Failed,
            'gateway_response' => mb_substr($message, 0, 1000),
        ])->save();
    }
}
