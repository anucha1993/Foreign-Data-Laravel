<?php

namespace App\Http\Controllers\Foreign;

use App\Http\Controllers\Controller;
use App\Services\ForeignData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(private readonly ForeignData $foreign) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get('foreign.expires_at', 0) > now()->getTimestamp()) {
            return redirect()->route('foreign.profile');
        }

        return response()->view('foreign.login', ['company' => config('foreign.company')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'passport' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:30'],
        ], [], [
            'passport' => __('เลขที่หนังสือเดินทาง'),
            'password' => __('รหัสผ่าน'),
        ]);

        $passport = ForeignData::normalizePassport($data['passport']);
        $ipKey = 'foreign-login:ip:'.$request->ip();
        $ppKey = 'foreign-login:pp:'.($passport ?? 'invalid');

        foreach ([[$ipKey, config('foreign.login_max_per_ip')], [$ppKey, config('foreign.login_max_per_passport')]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return $this->fail(__('ลองเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ :m นาทีแล้วลองใหม่', [
                    'm' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60)),
                ]));
            }
        }

        try {
            $id = $passport ? $this->foreign->findForLogin($passport, $data['password']) : null;
        } catch (\Throwable $e) {
            report($e);

            return $this->fail(__('ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้งในอีกสักครู่'));
        }

        if (! $id) {
            RateLimiter::hit($ipKey, 60);
            RateLimiter::hit($ppKey, 15 * 60);

            // ข้อความกลางๆ ไม่บอกว่าผิดที่เลขพาสปอร์ตหรือรหัสผ่าน
            return $this->fail(__('เลขที่หนังสือเดินทางหรือรหัสผ่านไม่ถูกต้อง'));
        }

        RateLimiter::clear($ppKey);
        $request->session()->regenerate();
        $request->session()->put([
            'foreign.id' => $id,
            'foreign.expires_at' => now()->getTimestamp() + config('foreign.session_minutes') * 60,
        ]);

        return redirect()->route('foreign.profile');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['foreign.id', 'foreign.expires_at']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('foreign.login')->with('notice', __('ออกจากระบบเรียบร้อยแล้ว'));
    }

    private function fail(string $message): RedirectResponse
    {
        return back()->withInput(request()->only('passport'))->withErrors(['login' => $message]);
    }
}
