@props(['headline', 'url', 'button'])
{{-- เลย์เอาต์อีเมล: inline style ทั้งหมด (โปรแกรมอีเมลส่วนใหญ่ไม่อ่าน <style>/CSS ภายนอก) --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f7f4;font-family:'IBM Plex Sans Thai','Sarabun','Noto Sans','Segoe UI',Arial,sans-serif;color:#17231c">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7f4;padding:24px 12px">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e1e8e3">
      <tr><td style="height:5px;line-height:5px;font-size:0;background:#f05523">&nbsp;</td><td style="height:5px;line-height:5px;font-size:0;background:#01ce55">&nbsp;</td></tr>
      <tr><td colspan="2" style="padding:20px 24px 4px">
        <table role="presentation" cellpadding="0" cellspacing="0"><tr>
          <td style="padding-right:10px"><img src="{{ asset('logo-192.png') }}" width="40" height="40" alt="" style="display:block;border-radius:50%"></td>
          <td>
            <div style="font-size:14px;font-weight:700;color:#c2410c">{{ config('foreign.company.name') }}</div>
            <div style="font-size:12px;color:#5b6a61">{{ __('ระบบข้อมูลแรงงาน') }}</div>
          </td>
        </tr></table>
      </td></tr>
      <tr><td colspan="2" style="padding:12px 24px 24px">
        <h1 style="margin:8px 0 14px;font-size:20px;line-height:1.35;color:#17231c">{{ $headline }}</h1>
        {{ $slot }}
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 6px"><tr><td style="border-radius:10px;background:#01ce55">
          <a href="{{ $url }}" style="display:inline-block;padding:12px 22px;font-size:15px;font-weight:700;color:#04301a;text-decoration:none">{{ $button }}</a>
        </td></tr></table>
        <p style="margin:6px 0 0;font-size:12px;color:#5b6a61;word-break:break-all">{{ $url }}</p>
      </td></tr>
    </table>
    <p style="margin:14px 0 0;font-size:12px;color:#5b6a61">{{ __('อีเมลนี้ส่งอัตโนมัติ กรุณาอย่าตอบกลับอีเมลนี้') }}</p>
  </td></tr>
</table>
</body>
</html>
