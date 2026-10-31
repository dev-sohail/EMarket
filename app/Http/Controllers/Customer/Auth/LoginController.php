<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\LoginRequest;
use App\Services\Cart\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LoginController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function showForm()
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('customer.dashboard');
        }

        return view('customer.auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        $lockKey = 'login_lock:'.md5(strtolower((string) $request->input('email')));
        if (Cache::has($lockKey)) {
            return back()->withInput()
                ->with('error', 'Account temporarily locked due to too many failed attempts. Try again later.');
        }

        if (Auth::guard('web')->attempt($credentials, $remember)) {
            $user = Auth::guard('web')->user();

            if (! $user->is_active) {
                Auth::guard('web')->logout();

                return back()->withInput()
                    ->with('error', 'Your account has been deactivated.');
            }

            $sessionId = $request->session()->getId();
            $customerId = $user->customer?->getKey();

            if ($customerId && $sessionId) {
                try {
                    $this->cartService->mergeSessionCart($sessionId, $customerId);
                } catch (\Exception $e) {
                    report($e);
                }
            }

            $request->session()->regenerate();

            Cache::forget('login_attempts:'.md5(strtolower((string) $request->input('email'))));
            Cache::forget('login_lock:'.md5(strtolower((string) $request->input('email'))));

            return redirect()->intended(route('customer.dashboard'))
                ->with('success', 'Welcome back!');
        }

        $attemptsKey = 'login_attempts:'.md5(strtolower((string) $request->input('email')));
        $attempts = (int) Cache::increment($attemptsKey);
        if ($attempts === 1) {
            Cache::put($attemptsKey, 1, now()->addMinutes(15));
        }
        if ($attempts >= 5) {
            Cache::put($lockKey, true, now()->addMinutes(15));
            Cache::forget($attemptsKey);
        }

        return back()->withInput()
            ->with('error', 'Invalid email or password.');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out.');
    }
}
