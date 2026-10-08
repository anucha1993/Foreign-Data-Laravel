@php
  $levelText = [
      'ok' => __('ใช้งานได้'),
      'warn' => __('ใกล้หมดอายุ'),
      'expired' => __('หมดอายุแล้ว'),
      'none' => __('ไม่มีข้อมูลวันหมดอายุ'),
  ];
  $tabs = [
      'status' => [__('สถานะเอกสาร'), '<path d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/>'],
      'files' => [__('ไฟล์เอกสาร'), '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>'],
      'info' => [__('ข้อมูลส่วนตัว'), '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>'],
      'tickets' => [__('แจ้งเรื่อง'), '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M9 11h6M9 14h4"/>'],
  ];
  $ticketUnread = $tickets->where('worker_unread', true)->count();
@endphp
<x-layout :title="$p['name'] ?: __('ข้อมูลแรงงาน')" :company="$company">
<x-slot:tools>
  <form method="post" action="{{ route('foreign.logout') }}">@csrf
    <button class="btn sm ghost" type="submit">{{ __('ออกจากระบบ') }}</button>
  </form>
</x-slot:tools>

<div class="shell app">
  {{-- ---------- แถบซ้าย (desktop) / หัวโปรไฟล์ + เมนูล่าง (mobile) ---------- --}}
  <aside class="side">
    <section class="card id-card">
      <div class="avatar">
        @if($p['hasPhoto'])
          <img src="{{ route('foreign.photo') }}" alt="" onerror="this.remove()">
        @endif
        <span>{{ $p['initials'] ?: '?' }}</span>
      </div>
      <div class="id-text">
        <h1>{{ $p['name'] }}</h1>
        @if($p['nameTh'])<div class="th">{{ $p['nameTh'] }}</div>@endif
        <div class="chips">
          @if($p['status'])<span class="chip ok">{{ $p['status'] }}</span>@endif
          @if($p['nationality'])<span class="chip">{{ $p['nationality'] }}</span>@endif
        </div>
      </div>
      <dl class="mini">
        @if($p['staffId'])<div><dt>{{ __('รหัสพนักงาน') }}</dt><dd>{{ $p['staffId'] }}</dd></div>@endif
        @if($p['passport'])<div><dt>Passport</dt><dd>{{ $p['passport'] }}</dd></div>@endif
        @if($p['birthday'])<div><dt>{{ __('วันเกิด') }}</dt><dd>{{ $p['birthday'] }}@if($p['age']) <span class="age">({{ $p['age'] }})</span>@endif</dd></div>@endif
        @if($p['employer'])<div><dt>{{ __('นายจ้าง') }}</dt><dd>{{ $p['employer'] }}</dd></div>@endif
      </dl>
    </section>

    <nav class="tabbar" aria-label="{{ __('เมนู') }}">
      @foreach($tabs as $id => [$label, $icon])
        <a href="#{{ $id }}" data-tab="{{ $id }}" @if($loop->first) aria-current="page"@endif>
          <span class="tab-ic">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
            @if($id === 'status' && $p['attention'])<span class="tab-badge">{{ $p['attention'] }}</span>@endif
            @if($id === 'files' && count($p['documents']))<span class="tab-count">{{ count($p['documents']) }}</span>@endif
            @if($id === 'tickets' && $ticketUnread)<span class="tab-badge">{{ $ticketUnread }}</span>@endif
          </span>
          <span class="tab-label">{{ $label }}</span>
        </a>
      @endforeach
    </nav>

    <div class="timer" data-expires="{{ $expiresAt }}">
      <span>{{ __('อัปเดตล่าสุด') }} <b>{{ $p['updatedAt'] ?: '-' }}</b></span>
      <span>{{ __('ออกจากระบบอัตโนมัติใน') }} <b id="countdown">--:--</b></span>
    </div>
  </aside>

  {{-- ---------- เนื้อหาแต่ละแท็บ ---------- --}}
  <div class="main">
    {{-- 1) สถานะเอกสาร (หน้าแรก) --}}
    <section class="panel" id="tab-status" data-panel="status" aria-labelledby="h-status">
      <header class="panel-head">
        <h2 id="h-status">{{ __('สถานะเอกสาร') }}</h2>
      </header>

      @if($p['cards'])
        @if($p['attention'])
          <div class="alert {{ collect($p['cards'])->contains('level', 'expired') ? 'bad' : 'warn' }}" role="status">
            {{ __('มี :n รายการที่ต้องดำเนินการ กรุณาติดต่อเจ้าหน้าที่หรือนายจ้าง', ['n' => $p['attention']]) }}
          </div>
        @else
          <div class="alert okay" role="status">{{ __('เอกสารทุกรายการยังไม่หมดอายุ') }}</div>
        @endif

        <div class="dcards">
          @foreach($p['cards'] as $c)
            <article class="dcard lv-border-{{ $c['level'] }}">
              <header class="dc-head">
                <h3>{{ $c['fullTitle'] }}</h3>
                <span class="badge lv-{{ $c['level'] }}">{{ $levelText[$c['level']] }}</span>
              </header>

              @if($c['days'] !== null)
              <div class="dc-days lv-text-{{ $c['level'] }}">
                @if($c['days'] === 0)
                  <b>0</b><span>{{ __('วัน') }}</span><span class="dc-cap">{{ __('หมดอายุวันนี้') }}</span>
                @elseif($c['days'] > 0)
                  <span class="dc-cap">{{ __('เหลืออีก') }}</span><b>{{ number_format($c['days']) }}</b><span>{{ __('วัน') }}</span>
                @else
                  <span class="dc-cap">{{ __('หมดอายุมาแล้ว') }}</span><b>{{ number_format(-$c['days']) }}</b><span>{{ __('วัน') }}</span>
                @endif
              </div>
              @endif

              {{-- แสดงเฉพาะช่องที่มีข้อมูล --}}
              <dl class="dc-meta">
                @if($c['number'])<div><dt>{{ $c['numberLabel'] }}</dt><dd>{{ $c['number'] }}</dd></div>@endif
                @if($c['expiry'])<div class="dc-exp"><dt>{{ __('วันหมดอายุ') }}</dt><dd>{{ $c['expiry'] }}</dd></div>@endif
                @foreach($c['details'] as $row)
                  <div><dt>{{ $row['label'] }}</dt><dd>{{ $row['value'] }}</dd></div>
                @endforeach
              </dl>
            </article>
          @endforeach
        </div>
      @else
        <section class="card empty">{{ __('ยังไม่มีข้อมูลเอกสาร') }}</section>
      @endif

      @if($company['phone'] || $company['line_url'])
        <section class="card">
          <h2>{{ __('ข้อมูลไม่ถูกต้อง หรือมีคำถาม?') }}</h2>
          <div class="contact">
            @if($company['phone'])<a class="btn ghost" href="tel:{{ preg_replace('/[^\d+]/', '', $company['phone']) }}">{{ __('โทร') }} {{ $company['phone'] }}</a>@endif
            @if($company['line_url'])<a class="btn line" href="{{ $company['line_url'] }}" target="_blank" rel="noopener">{{ __('ติดต่อทาง LINE') }}</a>@endif
          </div>
        </section>
      @endif
    </section>

    {{-- 2) ไฟล์เอกสาร: แตะทั้งแถวเพื่อเปิดดู, ไอคอนขวาเพื่อดาวน์โหลด --}}
    @php
      $ready = array_values(array_filter($p['documents'], fn ($d) => $d['available']));
      $missing = array_values(array_filter($p['documents'], fn ($d) => ! $d['available']));
      $fileType = function (array $d): array {
          $ext = strtoupper(pathinfo($d['filename'], PATHINFO_EXTENSION));
          $ext = $ext === 'JPEG' ? 'JPG' : $ext;

          return [$ext ?: 'FILE', in_array($ext, ['JPG', 'PNG', 'HEIC', 'WEBP'], true) ? 'ft-img' : ($ext === 'PDF' ? 'ft-pdf' : 'ft-other')];
      };
    @endphp
    <section class="panel" id="tab-files" data-panel="files" aria-labelledby="h-files">
      <header class="panel-head">
        <h2 id="h-files">{{ __('ไฟล์เอกสาร') }}</h2>
        <p class="muted">{{ __('แตะรายการเพื่อเปิดดู หรือแตะไอคอนเพื่อดาวน์โหลด') }}</p>
      </header>

      @if($ready)
        <h3 class="list-title">{{ __('พร้อมเปิดดู') }} <span>{{ count($ready) }}</span></h3>
        <ul class="flist card">
          @foreach($ready as $d)
            @php [$ext, $ft] = $fileType($d); @endphp
            <li>
              <a class="frow" href="{{ route('foreign.document', $d['id']) }}" target="_blank" rel="noopener">
                <span class="doc-ic {{ $ft }}">{{ $ext }}</span>
                <span class="fbody">
                  <b>{{ $d['name'] }}</b>
                  <small>
                    @if($d['level'] === 'expired')<em class="fexp">{{ $d['status'] }}</em> · @endif{{ $d['filename'] }}
                  </small>
                </span>
                <svg class="fchev" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
              </a>
              <a class="fdl" href="{{ route('foreign.document', [$d['id'], 'download' => 1]) }}" aria-label="{{ __('ดาวน์โหลด') }} {{ $d['name'] }}" title="{{ __('ดาวน์โหลด') }}">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11"/><path d="M7 10l5 5 5-5"/><path d="M5 20h14"/></svg>
              </a>
            </li>
          @endforeach
        </ul>
      @endif

      @if($missing)
        <h3 class="list-title">{{ __('ยังไม่มีไฟล์') }} <span>{{ count($missing) }}</span></h3>
        <ul class="flist card is-missing">
          @foreach($missing as $d)
            <li>
              <div class="frow">
                <span class="doc-ic ft-other">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/></svg>
                </span>
                <span class="fbody"><b>{{ $d['name'] }}</b></span>
              </div>
            </li>
          @endforeach
        </ul>
      @endif

      @if(! $p['documents'])
        <section class="card empty">{{ __('ยังไม่มีเอกสารในระบบ') }}</section>
      @endif
    </section>

    {{-- 3) ข้อมูลส่วนตัว (พับเก็บ เปิดเฉพาะหมวดแรก) --}}
    <section class="panel" id="tab-info" data-panel="info" aria-labelledby="h-info">
      <header class="panel-head">
        <div class="panel-head-row">
          <div>
            <h2 id="h-info">{{ __('ข้อมูลส่วนตัว') }}</h2>
            <p class="muted">{{ __('แตะหัวข้อเพื่อเปิด/ปิด') }}</p>
          </div>
          <button type="button" class="btn sm accent" data-toggle-all data-open="{{ __('เปิดทั้งหมด') }}" data-close="{{ __('ปิดทั้งหมด') }}">{{ __('เปิดทั้งหมด') }}</button>
        </div>
      </header>
      @forelse($p['info'] as $s)
        <details class="card acc" @if($loop->first) open @endif>
          <summary><span>{{ $s['title'] }}</span><small class="acc-count">{{ count($s['rows']) }}</small></summary>
          <dl class="kv">
            @foreach($s['rows'] as $row)
              <div>
                <dt>{{ $row['label'] }}</dt>
                @if($row['type'] === 'phone')
                  <dd><a href="tel:{{ preg_replace('/[^\d+]/', '', $row['value']) }}">{{ $row['value'] }}</a></dd>
                @else
                  <dd>{{ $row['value'] }}</dd>
                @endif
              </div>
            @endforeach
          </dl>
          <a class="acc-fix" href="{{ route('foreign.tickets.create', ['type' => 'correction', 'section' => $s['id']]) }}">{{ __('ข้อมูลไม่ถูกต้อง? ขอแก้ไข') }}</a>
        </details>
      @empty
        <section class="card empty">{{ __('ยังไม่มีข้อมูล') }}</section>
      @endforelse
    </section>

    {{-- 4) แจ้งเรื่อง / ขอแก้ไขข้อมูล --}}
    <section class="panel" id="tab-tickets" data-panel="tickets" aria-labelledby="h-tickets">
      <header class="panel-head">
        <h2 id="h-tickets">{{ __('แจ้งเรื่อง') }}</h2>
        <p class="muted">{{ __('แจ้งปัญหาหรือขอแก้ไขข้อมูล เจ้าหน้าที่จะตอบกลับในหน้านี้') }}</p>
      </header>
      <div class="tk-actions">
        <a class="tk-action" href="{{ route('foreign.tickets.create', ['type' => 'problem']) }}">
          <span class="tk-ic ic-accent"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg></span>
          <b>{{ __('แจ้งปัญหา') }}</b>
        </a>
        <a class="tk-action" href="{{ route('foreign.tickets.create', ['type' => 'correction']) }}">
          <span class="tk-ic ic-brand"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/></svg></span>
          <b>{{ __('ขอแก้ไขข้อมูล') }}</b>
        </a>
      </div>

      @if($tickets->isNotEmpty())
        <h3 class="list-title">{{ __('เรื่องที่แจ้ง') }} <span>{{ $tickets->count() }}</span></h3>
        <ul class="flist card">
          @foreach($tickets as $t)
            <li>
              <a class="frow" href="{{ route('foreign.tickets.show', $t) }}">
                <span class="doc-ic {{ $t->category === 'correction' ? 'ft-img' : 'ft-pdf' }}">
                  @if($t->category === 'correction')
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4z"/></svg>
                  @else
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                  @endif
                </span>
                <span class="fbody">
                  <b>@if($t->worker_unread)<i class="dot-new" aria-label="{{ __('มีข้อความใหม่') }}"></i>@endif{{ $t->category === 'correction' ? __($t->subject) : $t->subject }}</b>
                  <small>{{ $t->code }} · {{ \App\Support\ForeignProfile::dateTime($t->last_activity_at?->toIso8601String()) }}</small>
                </span>
                <span class="badge lv-{{ \App\Models\Ticket::STATUS_LEVEL[$t->status] ?? 'none' }}">{{ $t->statusLabel() }}</span>
              </a>
            </li>
          @endforeach
        </ul>
      @else
        <section class="card empty">{{ __('ยังไม่มีเรื่องที่แจ้ง') }}</section>
      @endif
    </section>
  </div>
</div>

<script>
(function () {
  // ---- แท็บ: จำแท็บไว้ใน hash (#status/#files/#info) ให้ปุ่มย้อนกลับและรีเฟรชใช้ได้ ----
  var panels = document.querySelectorAll('[data-panel]');
  var links = document.querySelectorAll('[data-tab]');
  document.documentElement.classList.add('js-tabs');
  function show(id, scroll) {
    if (!document.querySelector('[data-panel="' + id + '"]')) id = 'status';
    panels.forEach(function (el) { el.hidden = el.dataset.panel !== id; });
    links.forEach(function (a) {
      if (a.dataset.tab === id) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
    });
    if (scroll) window.scrollTo(0, 0);
  }
  links.forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      if (location.hash !== '#' + a.dataset.tab) history.pushState(null, '', '#' + a.dataset.tab);
      show(a.dataset.tab, true);
    });
  });
  window.addEventListener('popstate', function () { show(location.hash.slice(1), true); });
  // id ของแท็บเป็น tab-xxx จึงไม่มี anchor ให้ browser เลื่อนไปเอง -> เปิดที่บนสุดเสมอเหมือนแอป
  show(location.hash.slice(1), false);

  // ---- เปิด/ปิดทุกหมวดในแท็บข้อมูลส่วนตัว ----
  var toggle = document.querySelector('[data-toggle-all]');
  if (toggle) toggle.addEventListener('click', function () {
    var accs = document.querySelectorAll('details.acc');
    var open = Array.prototype.some.call(accs, function (d) { return !d.open; });
    accs.forEach(function (d) { d.open = open; });
    toggle.textContent = open ? toggle.dataset.close : toggle.dataset.open;
  });

  // ---- นับถอยหลังออกจากระบบ ----
  var el = document.getElementById('countdown');
  var end = Number(document.querySelector('[data-expires]').dataset.expires) * 1000;
  function tick() {
    var s = Math.max(0, Math.round((end - Date.now()) / 1000));
    el.textContent = String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    if (s === 0) { location.href = @json(route('foreign.login')); return; }
    setTimeout(tick, 1000);
  }
  tick();
})();
</script>
</x-layout>
