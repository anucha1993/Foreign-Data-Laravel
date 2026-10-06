@props(['name' => 'images'])
@php
  $max = config('foreign.tickets.max_images');
  $maxKb = config('foreign.tickets.max_image_kb');
@endphp
{{-- แนบรูปหลายรูป: พรีวิว + ลบออกได้ + ตรวจขนาดก่อนส่ง (server ตรวจซ้ำอีกครั้ง) --}}
<div class="uploader" data-uploader data-max="{{ $max }}" data-max-bytes="{{ $maxKb * 1024 }}"
     data-msg-size="{{ __('รูป :name ใหญ่เกิน 5MB', ['name' => '{name}']) }}"
     data-msg-count="{{ __('แนบได้สูงสุด :n รูป', ['n' => $max]) }}">
  <label class="up-btn">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M21 17l-5-5-8 8"/></svg>
    <span>{{ __('แนบรูปภาพ') }}</span>
    <input type="file" name="{{ $name }}[]" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple hidden>
  </label>
  <small class="up-hint">{{ __('ได้สูงสุด :n รูป รูปละไม่เกิน 5MB', ['n' => $max]) }}</small>
  <div class="up-error" role="alert" hidden></div>
  <ul class="up-list"></ul>
</div>

@once
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-uploader]').forEach(function (box) {
    var input = box.querySelector('input[type=file]');
    var list = box.querySelector('.up-list');
    var err = box.querySelector('.up-error');
    var max = Number(box.dataset.max), maxBytes = Number(box.dataset.maxBytes);
    var files = [];
    var form = box.closest('form');

    function sync() {
      // เก็บไฟล์ที่เลือกไว้ใน input จริง เพื่อส่งไปกับฟอร์ม
      var dt = new DataTransfer();
      files.forEach(function (f) { dt.items.add(f); });
      input.files = dt.files;
      list.innerHTML = '';
      files.forEach(function (f, i) {
        var li = document.createElement('li');
        var img = document.createElement('img');
        img.alt = '';
        if (/^image\/(jpeg|png|webp|gif)$/.test(f.type)) img.src = URL.createObjectURL(f);
        var rm = document.createElement('button');
        rm.type = 'button'; rm.className = 'up-rm'; rm.setAttribute('aria-label', 'remove'); rm.textContent = '×';
        rm.onclick = function () { files.splice(i, 1); sync(); };
        var size = document.createElement('span');
        size.className = 'up-size'; size.textContent = (f.size / 1048576).toFixed(1) + ' MB';
        li.append(img, size, rm);
        list.append(li);
      });
    }
    function showError(msgs) {
      err.hidden = !msgs.length;
      err.textContent = msgs.join(' · ');
    }
    input.addEventListener('change', function () {
      var msgs = [];
      Array.prototype.forEach.call(input.files, function (f) {
        if (f.size > maxBytes) { msgs.push(box.dataset.msgSize.replace('{name}', f.name)); return; }
        if (files.length >= max) { if (msgs.indexOf(box.dataset.msgCount) < 0) msgs.push(box.dataset.msgCount); return; }
        files.push(f);
      });
      showError(msgs);
      sync();
    });
    if (form) form.addEventListener('submit', function () {
      var btn = form.querySelector('[type=submit]');
      if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }
    });
  });
});
</script>
@endonce
