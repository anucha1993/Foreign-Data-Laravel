<x-admin-layout title="เข้าสู่ระบบ Admin" :company="$company">
<section class="card login-card admin-login">
  <h1>เข้าสู่ระบบ Admin</h1>
  <p class="lead">สำหรับเจ้าหน้าที่ดูแลเรื่องที่แรงงานแจ้งเข้ามา</p>
  @if($errors->any())
    <div class="alert bad" role="alert">{{ $errors->first() }}</div>
  @endif
  <form method="post" action="{{ route('admin.login.submit') }}">
    @csrf
    <div class="field">
      <label for="email">อีเมล</label>
      <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
    </div>
    <div class="field">
      <label for="password">รหัสผ่าน</label>
      <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
    </div>
    <label class="check"><input type="checkbox" name="remember" value="1"> จดจำการเข้าสู่ระบบ</label>
    <button class="btn block" type="submit">เข้าสู่ระบบ</button>
  </form>
</section>
</x-admin-layout>
