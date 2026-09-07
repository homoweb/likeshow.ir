<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Review step before jumping to the gateway.
     */
    public function review(Request $request, Order $order): InertiaResponse
    {
        $this->authorizeOrder($request, $order);

        return Inertia::render('Main/PaymentReview', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'product_title' => $order->product_title,
                'target_username' => $order->target_username,
                'quantity' => $order->quantity,
                'total_price' => $order->total_price,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
            ],
        ]);
    }

    /**
     * Start a payment at the gateway. Responds with the gateway's HTML
     * (auto-submitting form for real gateways, the built-in fake gateway
     * page for the local driver).
     */
    public function start(Request $request, Order $order): Response|RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        abort_unless($order->payment_status->value === 'unpaid', 404, 'این سفارش قابل پرداخت نیست.');

        try {
            $payment = $this->paymentService->start($order);
        } catch (PurchaseFailedException|InvalidPaymentException $e) {
            return redirect()
                ->route('main.payment.review', $order)
                ->with('error', $e->getMessage() ?: 'خطا در ارتباط با درگاه پرداخت. لطفاً دوباره تلاش کنید.');
        }

        $request->session()->put("payable_orders.{$order->id}", true);

        return response($this->paymentService->redirectionFormOf($payment)->render());
    }

    /**
     * Gateway callback (GET from the gateway, POST for some drivers).
     */
    public function callback(Request $request): RedirectResponse
    {
        $result = $this->paymentService->verifyCallback($request);

        $order = $result->payment?->order;

        if ($order === null) {
            return redirect()->route('main.home')->with(
                'error',
                $result->message ?? 'تراکنش یافت نشد.',
            );
        }

        if ($result->isSuccess()) {
            return redirect()->route('main.payment.result', $order)
                ->with('success', 'پرداخت شما با موفقیت انجام شد.');
        }

        return redirect()->route('main.payment.result', $order)
            ->with('error', $result->message ?? 'پرداخت ناموفق بود.');
    }

    /**
     * The payment result page for an order.
     */
    public function result(Request $request, Order $order): InertiaResponse
    {
        $this->authorizeOrder($request, $order);

        return Inertia::render('Main/PaymentResult', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'product_title' => $order->product_title,
                'target_username' => $order->target_username,
                'quantity' => $order->quantity,
                'total_price' => $order->total_price,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'paid_at' => $order->paid_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * An order may be accessed by its owner or by a guest whose browser
     * created it during this session.
     */
    private function authorizeOrder(Request $request, Order $order): void
    {
        $user = $request->user();

        if ($user !== null && $order->user_id === $user->getKey()) {
            return;
        }

        if ($request->session()->has("payable_orders.{$order->id}")) {
            return;
        }

        abort(403, 'شما به این سفارش دسترسی ندارید.');
    }
}
