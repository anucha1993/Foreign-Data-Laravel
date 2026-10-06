@php use App\Models\Ticket; @endphp
<x-mail.layout :headline="$headline" :url="$url" button="เปิดดูเรื่องในระบบ Admin">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse">
    @foreach([
      'รหัสเรื่อง' => $ticket->code,
      'ประเภท' => Ticket::CATEGORIES[$ticket->category] ?? $ticket->category,
      'หัวข้อ' => $ticket->subject,
      'แรงงาน' => trim($ticket->worker_name.' · '.$ticket->passport, ' ·'),
      'นายจ้าง' => $ticket->employer,
      'สถานะ' => Ticket::STATUSES[$ticket->status] ?? $ticket->status,
    ] as $label => $value)
      @if($value)
        <tr>
          <td style="padding:6px 0;color:#5b6a61;width:110px;vertical-align:top;border-bottom:1px solid #eef2ef">{{ $label }}</td>
          <td style="padding:6px 0;font-weight:600;border-bottom:1px solid #eef2ef">{{ $value }}</td>
        </tr>
      @endif
    @endforeach
  </table>

  @if($ticket->category === 'correction' && $event === 'opened')
    <div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:#e0f8ea;font-size:14px">
      <div style="color:#5b6a61;font-size:12px">ขอแก้ไข "{{ $ticket->field_label }}"</div>
      <div><s style="color:#5b6a61">{{ $ticket->current_value ?: '—' }}</s> → <b>{{ $ticket->requested_value }}</b></div>
    </div>
  @endif

  @if($ticketMessage->body)
    <div style="margin-top:14px;padding:12px 14px;border-radius:10px;background:#f5f9f6;border-left:3px solid #f05523;font-size:14px;white-space:pre-wrap">{{ $ticketMessage->body }}</div>
  @endif
  @if($attachmentCount)
    <p style="margin:10px 0 0;font-size:13px;color:#5b6a61">แนบรูปภาพ {{ $attachmentCount }} รูป (ดูในระบบ Admin)</p>
  @endif
</x-mail.layout>
