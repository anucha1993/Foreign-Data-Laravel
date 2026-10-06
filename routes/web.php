<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Foreign\AuthController;
use App\Http\Controllers\Foreign\ProfileController;
use App\Http\Controllers\Foreign\TicketController;
use App\Http\Middleware\AdminLocale;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/healthz', fn () => response()->json(['ok' => true]));

Route::redirect('/', '/foreign');

// ---------- แรงงาน ----------
Route::prefix('foreign')->name('foreign.')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1')->name('login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(['foreign.auth', 'throttle:120,1'])->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('profile');
        Route::get('/photo', [ProfileController::class, 'photo'])->name('photo');
        Route::get('/documents/{rowId}', [ProfileController::class, 'document'])->name('document');

        // แจ้งเรื่อง / ขอแก้ไขข้อมูล
        Route::get('/tickets/new', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [TicketController::class, 'store'])->middleware('throttle:10,60')->name('tickets.store');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->middleware('throttle:30,60')->name('tickets.reply');
        Route::get('/tickets/{ticket}/files/{attachment}', [TicketController::class, 'attachment'])->name('tickets.attachment');
    });
});

// ---------- Admin ----------
Route::prefix('admin')->name('admin.')->middleware(AdminLocale::class)->group(function () {
    Route::get('/login', [AdminAuthController::class, 'show'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:20,1')->name('login.submit');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::middleware('auth')->group(function () {
        Route::redirect('/', '/admin/tickets');
        Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('tickets.reply');
        Route::post('/tickets/{ticket}/translate', [AdminTicketController::class, 'translate'])->middleware('throttle:60,1')->name('tickets.translate');
        Route::get('/tickets/{ticket}/files/{attachment}', [AdminTicketController::class, 'attachment'])->name('tickets.attachment');
    });
});

// หน้าไม่พบ: ไม่ใช้ session — ไม่ให้ URL ที่ไม่มีจริง (เช่น /favicon.ico, /.well-known/... ที่ Chrome DevTools เรียกเอง)
// ถูกจำเป็น "หน้าก่อนหน้า" แล้วทำให้ back() / validation error พาผู้ใช้มาหน้านี้
Route::fallback(fn () => response()->view('foreign.message', [
    'title' => __('ไม่พบหน้านี้'),
    'message' => __('กรุณาเข้าสู่ระบบจากหน้าแรก'),
    'company' => config('foreign.company'),
], 404))->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class]);
