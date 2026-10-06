<x-layout :title="__('เข้าสู่ระบบ')" :company="$company">
<div class="login">
  <section class="login-hero">
    <img class="hero-logo" src="{{ asset('logo-512.png') }}" alt="" width="120" height="120">
    <h2>{{ __('ตรวจสอบข้อมูลและเอกสารของคุณ') }}</h2>
    <p>{{ __('ดูข้อมูลส่วนตัว วันหมดอายุเอกสาร และเปิดไฟล์เอกสารของคุณได้ทุกที่ ทุกเวลา') }}</p>
    <ul>
      <li><i>✓</i><span>{{ __('วันหมดอายุ Passport, VISA, Work Permit และรายงานตัว 90 วัน') }}</span></li>
      <li><i>✓</i><span>{{ __('เปิดดูและดาวน์โหลดเอกสารประจำตัว') }}</span></li>
      <li><i>✓</i><span>{{ __('ข้อมูลอัปเดตจากระบบของบริษัทโดยตรง') }}</span></li>
    </ul>
  </section>

  <section class="card login-card">
    <h1>{{ __('เข้าสู่ระบบ') }}</h1>
    <p class="lead">{{ __('กรอกเลขที่หนังสือเดินทางและรหัสผ่านของคุณ') }}</p>

    @if(session('notice'))
      <div class="alert info" role="status">{{ session('notice') }}</div>
    @endif
    @if($errors->any())
      <div class="alert bad" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('foreign.login.submit') }}" novalidate>
      @csrf
      <div class="field">
        <label for="passport">{{ __('เลขที่หนังสือเดินทาง') }}@if(app()->getLocale() === 'th') (Passport No.)@endif</label>
        <input class="input upper" id="passport" name="passport" value="{{ old('passport') }}" required
               autocomplete="username" autocapitalize="characters" spellcheck="false" maxlength="30" placeholder="{{ __('เช่น') }} MH1234567" autofocus>
      </div>
      <div class="field">
        <label for="password">{{ __('รหัสผ่าน') }}@if(app()->getLocale() === 'th') (Password)@endif</label>
        <input class="input" id="password" name="password" type="password" required
               inputmode="numeric" autocomplete="current-password" maxlength="30" placeholder="{{ __('รหัสผ่าน 8 หลัก') }}">
        <small>{{ __('หากไม่ทราบรหัสผ่าน กรุณาติดต่อเจ้าหน้าที่') }}</small>
      </div>
      <button class="btn block" type="submit">{{ __('เข้าสู่ระบบ') }}</button>
    </form>

    <p class="privacy">
      <span aria-hidden="true">🔒</span>
      <span>{{ __('ข้อมูลของคุณเป็นความลับ ระบบจะออกจากระบบอัตโนมัติเมื่อครบ :m นาที', ['m' => config('foreign.session_minutes')]) }}</span>
    </p>
  </section>
</div>
</x-layout>
