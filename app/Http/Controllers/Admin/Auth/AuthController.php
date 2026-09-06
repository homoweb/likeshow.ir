<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * The admin login page.
     */
    public function showLogin(): Response
    {
        return Inertia::render('Admin/Auth/Login');
    }

    /**
     * Authenticate an administrator.
     */
    public function login(Request $request): RedirectResponse
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
            return back()->withErrors(['email' => 'حساب کاربری شما غیرفعال است.']);
        }

        if (! $user->hasRole('admin')) {
            return back()->withErrors(['email' => 'این بخش مخصوص مدیران سیستم است.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('admin.users.index'));
    }

    /**
     * Log the administrator out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
