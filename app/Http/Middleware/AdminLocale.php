<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** หน้า Admin เป็นภาษาไทยเสมอ (ไม่ใช้ภาษาที่แรงงานเลือกไว้ใน cookie) */
class AdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('th');

        return $next($request);
    }
}
