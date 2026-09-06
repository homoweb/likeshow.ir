<?php

namespace App\Http\Controllers\Main;

use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\PriceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PriceCalculator $priceCalculator,
        private readonly OrderService $orderService,
    ) {}

    /**
     * The quantity selection step for a product.
     */
    public function show(Product $product): Response
    {
        abort_unless($product->is_active, 404);

        return Inertia::render('Main/Checkout', [
            // resolve() unwraps the resource so props arrive without the
            // extra {data: ...} envelope added by resource serialization.
            'product' => (new ProductResource($product->load('prices')))->resolve(),
        ]);
    }

    /**
     * Validate the order and either create it (logged-in users) or keep a
     * draft in the session so guests can continue right after login.
     */
    public function store(Request $request, Product $product): SymfonyResponse
    {
        abort_unless($product->is_active, 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer'],
            'target_username' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9._]{1,30}$/',
            ],
        ], [
            'quantity.required' => 'مقدار مورد نظر را انتخاب کنید.',
            'quantity.integer' => 'مقدار انتخابی معتبر نیست.',
            'target_username.required' => 'آیدی پیج هدف را وارد کنید.',
            'target_username.regex' => 'آیدی پیج معتبر نیست (فقط حروف انگلیسی، عدد، نقطه و آندرلاین).',
        ]);

        $quantity = (int) $validated['quantity'];
        $targetUsername = (string) $validated['target_username'];

        // Validate against business rules early so guests get feedback too.
        try {
            $this->priceCalculator->validateQuantity($product, $quantity);
        } catch (CheckoutException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        $user = $request->user();

        if ($user === null) {
            $request->session()->put('checkout_draft', [
                'product_id' => $product->getKey(),
                'quantity' => $quantity,
                'target_username' => $targetUsername,
            ]);

            $request->session()->flash(
                'info',
                'برای تکمیل خرید ابتدا وارد حساب کاربری خود شوید.',
            );

            // The frontend posts this form via Inertia (XHR). Inertia::location
            // answers with 409 + X-Inertia-Location (a full client-side page
            // visit to the panel login) for Inertia requests and falls back
            // to a regular redirect otherwise.
            return Inertia::location(route('panel.login'));
        }

        $order = $this->orderService->createFromCheckout($user, $product, $quantity, $targetUsername);

        return redirect()->route('main.payment.review', $order);
    }

    /**
     * Resume a guest checkout draft saved before the login round-trip.
     */
    public function resume(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('main.home');
        }

        /** @var array<string, mixed>|null $draft */
        $draft = $request->session()->get('checkout_draft');

        if (! is_array($draft) || ! isset($draft['product_id'], $draft['quantity'], $draft['target_username'])) {
            return redirect()->route('main.home');
        }

        $product = Product::query()->find((int) $draft['product_id']);

        if ($product === null || ! $product->is_active) {
            $request->session()->forget('checkout_draft');

            return redirect()->route('main.home')->with('error', 'محصول انتخابی دیگر در دسترس نیست.');
        }

        $request->session()->forget('checkout_draft');

        $order = $this->orderService->createFromCheckout(
            $user,
            $product,
            (int) $draft['quantity'],
            (string) $draft['target_username'],
        );

        return redirect()->route('main.payment.review', $order);
    }
}
