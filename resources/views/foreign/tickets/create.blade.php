@php
  // จัดกลุ่มฟิลด์ตามหมวด สำหรับ <optgroup>
  $groups = [];
  foreach ($fields as $key => $f) {
      $groups[$f['section']][$key] = $f;
  }
  $sectionTitle = \App\Support\ForeignProfile::SECTIONS[$section][0] ?? null;
@endphp
<x-layout :title="__('แจ้งเรื่องใหม่')" :company="$company">
<div class="subpage">
  <a class="back" href="{{ route('foreign.profile') }}#tickets">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
    {{ __('กลับ') }}
  </a>

  <header class="panel-head">
    <h2>{{ __('แจ้งเรื่องใหม่') }}</h2>
    <p class="muted">{{ __('เจ้าหน้าที่จะตรวจสอบและตอบกลับในหน้านี้') }}</p>
  </header>

  <form method="post" action="{{ route('foreign.tickets.store') }}" enctype="multipart/form-data" class="card form-card" data-ticket-form>
    @csrf
    @if($errors->any())
      <div class="alert bad" role="alert">{{ $errors->first() }}</div>
    @endif

    <fieldset class="seg" role="radiogroup" aria-label="{{ __('ประเภทเรื่อง') }}">
      @foreach(\App\Models\Ticket::CATEGORIES as $value => $label)
        <label>
          <input type="radio" name="category" value="{{ $value }}" @checked(old('category', $category) === $value)>
          <span>{{ __($label) }}</span>
        </label>
      @endforeach
    </fieldset>

    {{-- แจ้งปัญหา --}}
    <div data-show-for="problem">
      <div class="field">
        <label for="subject">{{ __('หัวข้อ') }}</label>
        <input class="input" id="subject" name="subject" maxlength="150" value="{{ old('subject') }}" placeholder="{{ __('เช่น เปิดไฟล์เอกสารไม่ได้') }}">
      </div>
    </div>

    {{-- ขอแก้ไขข้อมูล --}}
    <div data-show-for="correction">
      <div class="field">
        <label for="field_key">{{ __('ข้อมูลที่ต้องการแก้ไข') }}</label>
        <select class="input" id="field_key" name="field_key">
          <option value="">{{ __('— เลือก —') }}</option>
          @foreach($groups as $title => $items)
            <optgroup label="{{ __($title) }}">
              @foreach($items as $key => $f)
                <option value="{{ $key }}" data-current="{{ $f['current'] }}"
                  @selected(old('field_key', $selectedField) === $key || (! old('field_key') && ! $selectedField && $sectionTitle === $title && $loop->first))>{{ __($f['label']) }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
        {{-- กล่องเลือกแบบค้นหาได้ (JS สร้างรายการจาก <select> ด้านบน; ไม่มี JS = ใช้ select ปกติ) --}}
        <div class="picker" data-picker hidden>
          <button type="button" class="input picker-btn" aria-haspopup="listbox" aria-expanded="false">
            <span class="picker-val"></span>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
          </button>
          <div class="picker-pop" hidden>
            <div class="picker-head">
              <b>{{ __('ข้อมูลที่ต้องการแก้ไข') }}</b>
              <button type="button" class="picker-close" aria-label="close">×</button>
            </div>
            <label class="picker-search">
              <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
              <input type="search" placeholder="{{ __('ค้นหา เช่น ชื่อ วีซ่า ที่อยู่') }}" autocomplete="off" aria-label="{{ __('ค้นหา') }}">
            </label>
            <ul class="picker-list" role="listbox"></ul>
            <div class="picker-empty" hidden>{{ __('ไม่พบรายการ') }}</div>
          </div>
        </div>
      </div>
      <div class="field current-box" hidden>
        <label>{{ __('ข้อมูลปัจจุบันในระบบ') }}</label>
        <div class="current-value"></div>
      </div>
      <div class="field">
        <label for="requested_value">{{ __('ข้อมูลที่ถูกต้อง') }}</label>
        <input class="input" id="requested_value" name="requested_value" maxlength="500" value="{{ old('requested_value') }}">
      </div>
    </div>

    <div class="field">
      <label for="body">{{ __('รายละเอียด') }} <span class="opt" data-show-for="correction">({{ __('ไม่บังคับ') }})</span></label>
      <textarea class="input" id="body" name="body" rows="5" maxlength="3000" placeholder="{{ __('อธิบายเพิ่มเติม เขียนเป็นภาษาของคุณได้') }}">{{ old('body') }}</textarea>
    </div>

    <div class="field">
      <x-image-upload />
      <small class="up-hint" data-show-for="correction">{{ __('แนะนำให้แนบรูปเอกสารที่ถูกต้อง เช่น หน้าพาสปอร์ต') }}</small>
    </div>

    <button class="btn block" type="submit">{{ __('ส่งเรื่อง') }}</button>
  </form>
</div>

<script>
// ---- กล่องเลือกข้อมูลแบบค้นหาได้ ----
(function () {
  var picker = document.querySelector('[data-picker]');
  var select = document.getElementById('field_key');
  if (!picker || !select) return;
  var btn = picker.querySelector('.picker-btn'), pop = picker.querySelector('.picker-pop');
  var search = picker.querySelector('input[type=search]'), list = picker.querySelector('.picker-list');
  var empty = picker.querySelector('.picker-empty');
  var norm = function (s) { return (s || '').toLowerCase().normalize('NFC'); };

  select.hidden = true;
  picker.hidden = false;

  // สร้างรายการจาก <optgroup>/<option>
  Array.prototype.forEach.call(select.querySelectorAll('optgroup'), function (g) {
    var head = document.createElement('li');
    head.className = 'picker-group'; head.textContent = g.label;
    list.append(head);
    Array.prototype.forEach.call(g.querySelectorAll('option'), function (o) {
      var li = document.createElement('li');
      li.className = 'picker-opt'; li.setAttribute('role', 'option'); li.tabIndex = -1;
      li.dataset.value = o.value;
      li.dataset.search = norm(o.textContent + ' ' + g.label + ' ' + (o.dataset.current || ''));
      var b = document.createElement('b'); b.textContent = o.textContent;
      var sm = document.createElement('small'); sm.textContent = o.dataset.current || '—';
      li.append(b, sm);
      li._group = head;
      list.append(li);
    });
  });
  var opts = list.querySelectorAll('.picker-opt');

  function label() {
    var o = select.options[select.selectedIndex];
    btn.querySelector('.picker-val').textContent = o ? o.textContent : '';
    btn.classList.toggle('is-empty', !select.value);
    opts.forEach(function (li) { li.setAttribute('aria-selected', li.dataset.value === select.value); });
  }
  function filter() {
    var q = norm(search.value.trim()), shown = 0;
    var groups = new Map();
    opts.forEach(function (li) {
      var ok = !q || li.dataset.search.indexOf(q) >= 0;
      li.hidden = !ok;
      if (ok) { shown++; groups.set(li._group, true); }
    });
    list.querySelectorAll('.picker-group').forEach(function (g) { g.hidden = !groups.has(g); });
    empty.hidden = shown > 0;
  }
  function open() {
    pop.hidden = false; btn.setAttribute('aria-expanded', 'true');
    document.body.classList.add('picker-open');
    search.value = ''; filter();
    setTimeout(function () { search.focus(); }, 30);
    var cur = list.querySelector('[aria-selected=true]');
    if (cur) cur.scrollIntoView({ block: 'center' });
  }
  function close() {
    pop.hidden = true; btn.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('picker-open');
  }
  function choose(li) {
    select.value = li.dataset.value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    label(); close(); btn.focus();
  }
  function visible() { return Array.prototype.filter.call(opts, function (li) { return !li.hidden; }); }

  btn.addEventListener('click', function () { pop.hidden ? open() : close(); });
  picker.querySelector('.picker-close').addEventListener('click', close);
  search.addEventListener('input', function () { filter(); list.scrollTop = 0; });
  list.addEventListener('click', function (e) { var li = e.target.closest('.picker-opt'); if (li) choose(li); });
  pop.addEventListener('keydown', function (e) {
    var v = visible(), i = v.indexOf(document.activeElement);
    if (e.key === 'Escape') { close(); btn.focus(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); (v[i + 1] || v[0])?.focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); (i > 0 ? v[i - 1] : search).focus(); }
    else if (e.key === 'Enter') { e.preventDefault(); var li = i >= 0 ? v[i] : v[0]; if (li) choose(li); }
  });
  document.addEventListener('click', function (e) { if (!pop.hidden && !picker.contains(e.target)) close(); });
  label();
})();

(function () {
  var form = document.querySelector('[data-ticket-form]');
  var select = form.querySelector('#field_key');
  var box = form.querySelector('.current-box');
  function category() { return (form.querySelector('input[name=category]:checked') || {}).value; }
  function update() {
    var c = category();
    form.querySelectorAll('[data-show-for]').forEach(function (el) { el.hidden = el.dataset.showFor !== c; });
    var opt = select.options[select.selectedIndex];
    var cur = opt ? opt.dataset.current : '';
    box.hidden = c !== 'correction' || !select.value;
    box.querySelector('.current-value').textContent = cur || '—';
  }
  form.addEventListener('change', update);
  update();
})();
</script>
</x-layout>
