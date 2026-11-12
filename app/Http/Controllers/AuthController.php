<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\Accounts;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.form', ['registering' => false]);
    }

    public function registerForm(): View
    {
        return view('auth.form', ['registering' => true]);
    }

    public function login(LoginRequest $request, Accounts $accounts): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            $user = $accounts->linkLegacyAccount($credentials['email'], $credentials['password']);

            if (! $user) {
                throw ValidationException::withMessages(['email' => 'These credentials could not be verified.']);
            }

            Auth::login($user, $remember);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(RegisterRequest $request, Accounts $accounts): RedirectResponse
    {
        $data = $request->validated();
        $user = $accounts->register($data['full_name'], $data['email'], $data['password']);
        Auth::login($user);
        $request->session()->regenerate();
        event(new Registered($user));

        return redirect()->route('dashboard')->with('status', 'Your CampusSpace account is ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function verificationNotice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail() ? redirect()->route('dashboard') : view('auth.verify');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('dashboard')->with('status', 'Your email has been verified.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'A verification link has been sent.');
    }
}
