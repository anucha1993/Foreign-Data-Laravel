<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** ต้องล็อกอินแล้ว และยังไม่เกินอายุ session (นับจากตอนล็อกอิน ไม่ต่ออายุอัตโนมัติ) */
class EnsureForeignSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('foreign.id');
        $expiresAt = (int) $request->session()->get('foreign.expires_at', 0);

        if (! $id || $expiresAt < now()->getTimestamp()) {
            $expired = (bool) $id;
            // ลบเฉพาะ session ของแรงงาน (ไม่กระทบ Admin ที่ล็อกอินใน browser เดียวกัน)
            $request->session()->forget(['foreign.id', 'foreign.expires_at']);

            return redirect()->route('foreign.login')
                ->with('notice', $expired ? __('หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบอีกครั้ง') : null);
        }

        $request->attributes->set('foreign_id', (string) $id);

        return $next($request);
    }
}
