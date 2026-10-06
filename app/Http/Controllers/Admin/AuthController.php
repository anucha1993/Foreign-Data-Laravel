<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.tickets.index');
        }

        return response()->view('admin.login', ['company' => config('foreign.company')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'ลองเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ '.ceil(RateLimiter::availableIn($key) / 60).' นาที']);
        }

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 15 * 60);

            return back()->withInput($request->only('email'))->withErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.tickets.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
