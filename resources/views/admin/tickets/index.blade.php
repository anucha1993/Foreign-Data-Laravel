@php
  use App\Models\Ticket;
  $tabs = ['active' => 'ที่ต้องดำเนินการ', 'open' => 'รอดำเนินการ', 'in_progress' => 'กำลังดำเนินการ', 'resolved' => 'ดำเนินการแล้ว', 'closed' => 'ปิดเรื่อง', 'all' => 'ทั้งหมด'];
  $countFor = fn ($k) => match ($k) {
      'active' => ($counts['open'] ?? 0) + ($counts['in_progress'] ?? 0),
      'all' => $counts->sum(),
      default => $counts[$k] ?? 0,
  };
@endphp
<x-admin-layout title="Ticket" :company="$company">
<div class="admin-wrap">
  <header class="panel-head panel-head-row">
    <div>
      <h2>เรื่องที่แรงงานแจ้ง</h2>
      <p class="muted">{{ $unread ? "มีเรื่องใหม่/ข้อความใหม่ {$unread} เรื่อง" : 'ไม่มีข้อความใหม่' }}</p>
    </div>
    <form method="get" class="admin-search">
      <input type="hidden" name="status" value="{{ $status }}">
      <input class="input" type="search" name="q" value="{{ $q }}" placeholder="ค้นหา รหัสเรื่อง / Passport / ชื่อ / นายจ้าง">
    </form>
  </header>

  <nav class="filter-tabs" aria-label="สถานะ">
    @foreach($tabs as $key => $label)
      <a href="{{ route('admin.tickets.index', array_filter(['status' => $key, 'q' => $q])) }}" @if($status === $key) aria-current="page" @endif>
        {{ $label }} <span>{{ $countFor($key) }}</span>
      </a>
    @endforeach
  </nav>

  @if($tickets->isEmpty())
    <section class="card empty">ไม่พบเรื่องที่ตรงกับเงื่อนไข</section>
  @else
    <div class="card admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>เรื่อง</th><th>แรงงาน</th><th>ประเภท</th><th>สถานะ</th><th>อัปเดตล่าสุด</th></tr>
        </thead>
        <tbody>
          @foreach($tickets as $t)
            <tr @class(['is-unread' => $t->admin_unread]) onclick="location.href='{{ route('admin.tickets.show', $t) }}'">
              <td data-label="เรื่อง">
                <a href="{{ route('admin.tickets.show', $t) }}"><b>@if($t->admin_unread)<i class="dot-new"></i>@endif{{ $t->code }}</b></a>
                <div class="sub">{{ $t->subject }}</div>
              </td>
              <td data-label="แรงงาน">
                <b>{{ $t->worker_name ?: '-' }}</b>
                <div class="sub">{{ $t->passport }} · {{ $t->employer }}</div>
              </td>
              <td data-label="ประเภท"><span class="chip {{ $t->category === 'correction' ? 'chip-brand' : 'chip-accent' }}">{{ Ticket::CATEGORIES[$t->category] ?? $t->category }}</span></td>
              <td data-label="สถานะ"><span class="badge lv-{{ Ticket::STATUS_LEVEL[$t->status] ?? 'none' }}">{{ Ticket::STATUSES[$t->status] ?? $t->status }}</span></td>
              <td data-label="อัปเดตล่าสุด" class="nowrap">{{ $t->last_activity_at?->timezone('Asia/Bangkok')->format('d/m/Y H:i') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="pager">{{ $tickets->links('admin.pagination') }}</div>
  @endif
</div>
</x-admin-layout>
