@php
  use App\Models\Ticket;
  use App\Support\Locales;
  $fmt = fn ($d) => $d?->timezone('Asia/Bangkok')->format('d/m/Y H:i');
  $hasWorkerText = $messages->contains(fn ($m) => $m->fromWorker() && $m->body);
@endphp
<x-admin-layout :title="$ticket->code" :company="$company">
<div class="admin-wrap">
  <a class="back" href="{{ route('admin.tickets.index') }}">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
    รายการเรื่อง
  </a>

  @if(session('notice'))
    <div class="alert okay" role="status">{{ session('notice') }}</div>
  @endif

  <div class="admin-grid">
    {{-- ---------- ซ้าย: บทสนทนา ---------- --}}
    <div class="admin-main">
      <section class="card tk-head">
        <div class="tk-meta">
          <span class="tk-code">{{ $ticket->code }}</span>
          <span class="badge lv-{{ Ticket::STATUS_LEVEL[$ticket->status] ?? 'none' }}">{{ Ticket::STATUSES[$ticket->status] }}</span>
        </div>
        <h2>{{ $ticket->subject }}</h2>
        <p class="muted">{{ Ticket::CATEGORIES[$ticket->category] }} · แจ้งเมื่อ {{ $fmt($ticket->created_at) }} · ภาษาที่แรงงานใช้: {{ Locales::has($ticket->locale) ? Locales::label($ticket->locale) : $ticket->locale }}</p>

        @if($ticket->category === 'correction')
          <dl class="tk-change">
            <div><dt>ช่องที่ขอแก้ไข</dt><dd>{{ $ticket->field_label }} <code>{{ $ticket->field_key }}</code></dd></div>
            <div><dt>ข้อมูลปัจจุบันในระบบ (ตอนแจ้ง)</dt><dd>{{ $ticket->current_value ?: '—' }}</dd></div>
            <div class="to"><dt>ขอแก้ไขเป็น</dt><dd>{{ $ticket->requested_value }}</dd></div>
          </dl>
        @endif
      </section>

      @if($hasWorkerText)
        <div class="translate-bar">
          @if($translate)
            <span class="muted">แสดงคำแปลภาษาไทยของข้อความแรงงาน</span>
            <a class="btn sm ghost" href="{{ route('admin.tickets.show', [$ticket, 'translate' => 0]) }}">ซ่อนคำแปล</a>
          @else
            <a class="btn sm accent" href="{{ route('admin.tickets.show', [$ticket, 'translate' => 1]) }}">แปลข้อความแรงงานเป็นภาษาไทย</a>
          @endif
        </div>
      @endif

      <ol class="thread">
        @foreach($messages as $m)
          <li class="msg {{ $m->fromWorker() ? 'them' : 'me' }}">
            <div class="bubble">
              <div class="who">{{ $m->fromWorker() ? ($ticket->worker_name ?: 'แรงงาน') : 'เจ้าหน้าที่ · '.($m->user?->name ?? '-') }} · <time>{{ $fmt($m->created_at) }}</time></div>
              @if($m->body)
                <p class="body">{{ $m->body }}</p>
                @if(isset($translations[$m->id]))
                  <p class="body tr"><span>แปล:</span> {{ $translations[$m->id] }}</p>
                @endif
                @if(! $m->fromWorker() && $m->body_translated)
                  <p class="body tr sent"><span>ส่งให้แรงงานเป็น{{ Locales::has($m->translated_locale) ? 'ภาษา '.Locales::label($m->translated_locale) : '' }}:</span> {{ $m->body_translated }}</p>
                @endif
              @endif
              @if($m->attachments->isNotEmpty())
                <div class="thumbs">
                  @foreach($m->attachments as $a)
                    <a href="{{ route('admin.tickets.attachment', [$ticket, $a]) }}" target="_blank" rel="noopener" title="{{ $a->original_name }} ({{ number_format($a->size / 1048576, 1) }} MB)">
                      @if(in_array($a->mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true))
                        <img src="{{ route('admin.tickets.attachment', [$ticket, $a]) }}" alt="{{ $a->original_name }}" loading="lazy">
                      @else
                        <span class="thumb-file">{{ strtoupper(pathinfo($a->original_name, PATHINFO_EXTENSION)) ?: 'FILE' }}</span>
                      @endif
                    </a>
                  @endforeach
                </div>
              @endif
            </div>
          </li>
        @endforeach
      </ol>

      <form method="post" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="card form-card">
        @csrf
        @if($errors->any())
          <div class="alert bad" role="alert">{{ $errors->first() }}</div>
        @endif
        <div class="field">
          <label for="body">ตอบกลับแรงงาน <span class="opt">(พิมพ์ภาษาไทยได้)</span></label>
          <textarea class="input" id="body" name="body" rows="4" maxlength="5000">{{ old('body') }}</textarea>
        </div>
        <div class="translate-box" data-translate-url="{{ route('admin.tickets.translate', $ticket) }}">
          <div class="reply-row">
            <label for="reply_locale">ส่งเป็นภาษา</label>
            <select class="input" id="reply_locale" name="reply_locale">
              @foreach(Locales::SUPPORTED as $code => [$label])
                <option value="{{ $code }}" @selected(old('reply_locale', $ticket->locale) === $code)>{{ $code === 'th' ? 'ไทย (ไม่แปล)' : $label }}{{ $code === $ticket->locale && $code !== 'th' ? ' — ภาษาที่แรงงานใช้' : '' }}</option>
              @endforeach
            </select>
            <button type="button" class="btn sm accent" data-translate-btn>แปลก่อนส่ง</button>
          </div>
          <div class="field translated-field" hidden>
            <label for="body_translated">ข้อความที่แรงงานจะเห็น <span class="opt">(แก้ไขได้)</span></label>
            <textarea class="input" id="body_translated" name="body_translated" rows="4" maxlength="5000">{{ old('body_translated') }}</textarea>
          </div>
          <small class="up-hint translate-hint">ไม่กด "แปลก่อนส่ง" = ระบบแปลให้อัตโนมัติตอนส่ง · แรงงานกดดูต้นฉบับภาษาไทยได้</small>
          <div class="up-error translate-error" role="alert" hidden></div>
        </div>
        <div class="field"><x-image-upload /></div>
        <div class="reply-row">
          <label for="status">สถานะ</label>
          <select class="input" id="status" name="status">
            @foreach(Ticket::STATUSES as $value => $label)
              <option value="{{ $value }}" @selected(old('status', $ticket->status === 'open' ? 'in_progress' : $ticket->status) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          <button class="btn" type="submit">บันทึก / ส่งคำตอบ</button>
        </div>
        <small class="up-hint">ไม่พิมพ์ข้อความ = เปลี่ยนสถานะอย่างเดียว</small>
      </form>
    </div>

    {{-- ---------- ขวา: ข้อมูลแรงงาน ---------- --}}
    <aside class="admin-side">
      <section class="card">
        <h2>ข้อมูลแรงงาน</h2>
        <dl class="mini">
          <div><dt>ชื่อ</dt><dd>{{ $ticket->worker_name ?: '-' }}</dd></div>
          <div><dt>Passport</dt><dd>{{ $ticket->passport ?: '-' }}</dd></div>
          <div><dt>นายจ้าง</dt><dd>{{ $ticket->employer ?: '-' }}</dd></div>
          <div><dt>CRM Record ID</dt><dd><code>{{ $ticket->foreign_id }}</code></dd></div>
        </dl>
        @if($ticket->category === 'correction')
          <p class="up-hint" style="margin-top:10px">แก้ข้อมูลใน Zoho CRM แล้วเปลี่ยนสถานะเป็น "ดำเนินการแล้ว" — หน้าแรงงานจะแสดงข้อมูลใหม่ภายใน 1 นาที</p>
        @endif
      </section>
    </aside>
  </div>
</div>
<script>
(function () {
  var box = document.querySelector('[data-translate-url]');
  if (!box) return;
  var body = document.getElementById('body'), sel = document.getElementById('reply_locale');
  var out = document.getElementById('body_translated'), field = box.querySelector('.translated-field');
  var btn = box.querySelector('[data-translate-btn]'), err = box.querySelector('.translate-error');
  var token = box.closest('form').querySelector('input[name=_token]').value;
  function sync() {
    var th = sel.value === 'th';
    btn.hidden = th;
    box.querySelector('.translate-hint').hidden = th;
    if (th) { field.hidden = true; out.value = ''; }
  }
  // แก้ข้อความไทย/เปลี่ยนภาษา -> คำแปลเดิมไม่ตรงแล้ว ต้องแปลใหม่
  function stale() { if (!field.hidden) { field.hidden = true; out.value = ''; } }
  if (out.value) field.hidden = false;
  sel.addEventListener('change', function () { stale(); sync(); });
  body.addEventListener('input', stale);
  btn.addEventListener('click', function () {
    err.hidden = true;
    if (!body.value.trim()) { body.focus(); return; }
    btn.disabled = true; btn.textContent = 'กำลังแปล…';
    fetch(box.dataset.translateUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({ text: body.value, locale: sel.value })
    }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'แปลไม่ได้'); return j; }); })
      .then(function (j) { out.value = j.text; field.hidden = false; out.focus(); })
      .catch(function (e) { err.textContent = e.message; err.hidden = false; })
      .finally(function () { btn.disabled = false; btn.textContent = 'แปลก่อนส่ง'; });
  });
  sync();
})();
</script>
</x-admin-layout>
