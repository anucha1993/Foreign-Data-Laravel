<x-mail.layout :headline="$headline" :url="$url" :button="__('เปิดดูเรื่องที่แจ้ง')">
  <p style="margin:0 0 12px;font-size:14px">{{ __('สวัสดีคุณ :name', ['name' => $ticket->worker_name ?: '-']) }}</p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse">
    <tr>
      <td style="padding:6px 0;color:#5b6a61;width:120px;border-bottom:1px solid #eef2ef">{{ __('รหัสเรื่อง') }}</td>
      <td style="padding:6px 0;font-weight:700;color:#c2410c;border-bottom:1px solid #eef2ef">{{ $ticket->code }}</td>
    </tr>
    <tr>
      <td style="padding:6px 0;color:#5b6a61;border-bottom:1px solid #eef2ef">{{ __('เรื่อง') }}</td>
      <td style="padding:6px 0;font-weight:600;border-bottom:1px solid #eef2ef">{{ $ticket->category === 'correction' ? $ticket->categoryLabel().': '.__($ticket->subject) : $ticket->subject }}</td>
    </tr>
    <tr>
      <td style="padding:6px 0;color:#5b6a61">{{ __('สถานะ') }}</td>
      <td style="padding:6px 0;font-weight:600">{{ $ticket->statusLabel() }}</td>
    </tr>
  </table>

  @if($event === 'received')
    <p style="margin:14px 0 0;font-size:14px">{{ __('เจ้าหน้าที่จะตรวจสอบและตอบกลับโดยเร็ว คุณจะได้รับอีเมลเมื่อมีคำตอบ') }}</p>
  @endif

  @if($staffMessage)
    <div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:#e0f8ea;border-left:3px solid #01ce55;font-size:14px">
      <div style="font-size:12px;color:#5b6a61;margin-bottom:4px">{{ __('ข้อความจากเจ้าหน้าที่') }}</div>
      <div style="white-space:pre-wrap">{{ $staffMessage }}</div>
    </div>
  @endif

  <p style="margin:14px 0 0;font-size:13px;color:#5b6a61">{{ __('เข้าสู่ระบบด้วยเลขที่หนังสือเดินทางเพื่อดูรายละเอียดและตอบกลับ') }}</p>
</x-mail.layout>
