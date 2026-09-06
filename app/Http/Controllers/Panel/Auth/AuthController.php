<?php

namespace App\Http\Controllers\Panel\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthController extends Controller
{
    /**
     * The panel login page.
     */
    public function showLogin(): Response
    {
        return Inertia::render('Panel/Auth/Login');
    }

    /**
     * The panel registration page.
     */
    public function showRegister(): Response
    {
        return Inertia::render('Panel/Auth/Register');
    }

    /**
     * Authenticate a user on the panel.
     */
    public function login(Request $request): SymfonyResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'ایمیل را وارد کنید.',
            'email.email' => 'ایمیل معتبر نیست.',
            'password.required' => 'رمز عبور را وارد کنید.',
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'اطلاعات ورود نادرست است.']);
        }

        if (! $user->isActive()) {
            return back()->withErrors(['email' => 'حساب کاربری شما غیرفعال است. با پشتیبانی تماس بگیرید.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Resume a guest checkout draft started on the main site.
        $draft = $request->session()->get('checkout_draft');

        if (is_array($draft) && isset($draft['product_id'])) {
            // The login form posts via Inertia (XHR). Inertia::location
            // answers with 409 + X-Inertia-Location, which the client turns
            // into a full page visit to the resume URL.
            return Inertia::location(route('main.checkout.resume'));
        }

        // Only honor intended URLs that live inside the panel. A stale
        // intended URL (stored before a guest redirect on the main site)
        // would otherwise send the user somewhere unexpected.
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && str_starts_with($intended, config('likeshow.panel_url').'/')) {
            return redirect()->away($intended);
        }

        return redirect()->route('panel.orders.index');
    }

    /**
     * Create a new panel account.
     */
    public function register(Request $request): SymfonyResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'نام را وارد کنید.',
            'email.required' => 'ایمیل را وارد کنید.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'password.required' => 'رمز عبور را وارد کنید.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        $user->assignRole('user');

        Auth::login($user);
        $request->session()->regenerate();

        $draft = $request->session()->get('checkout_draft');

        if (is_array($draft) && isset($draft['product_id'])) {
            // See login(): after the XHR register call the resume URL is
            // reached through a full page visit via Inertia::location.
            return Inertia::location(route('main.checkout.resume'));
        }

        return redirect()->route('panel.orders.index');
    }

    /**
     * Log the user out of the panel and return them to the landing page.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('main.home');
    }
}
