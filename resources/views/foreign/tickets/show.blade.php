@php
  $fmt = fn ($d) => $d ? \App\Support\ForeignProfile::dateTime($d->toIso8601String()) : '';
@endphp
<x-layout :title="$ticket->code" :company="$company">
<div class="subpage">
  <a class="back" href="{{ route('foreign.profile') }}#tickets">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
    {{ __('เรื่องที่แจ้ง') }}
  </a>

  @if(session('notice'))
    <div class="alert okay" role="status">{{ session('notice') }}</div>
  @endif

  <section class="card tk-head">
    <div class="tk-meta">
      <span class="tk-code">{{ $ticket->code }}</span>
      <span class="badge lv-{{ \App\Models\Ticket::STATUS_LEVEL[$ticket->status] ?? 'none' }}">{{ $ticket->statusLabel() }}</span>
    </div>
    <h2>{{ $ticket->category === 'correction' ? __($ticket->subject) : $ticket->subject }}</h2>
    <p class="muted">{{ $ticket->categoryLabel() }} · {{ $fmt($ticket->created_at) }}</p>

    @if($ticket->category === 'correction')
      <dl class="tk-change">
        <div><dt>{{ __('ข้อมูลปัจจุบันในระบบ') }}</dt><dd>{{ $ticket->current_value ?: '—' }}</dd></div>
        <div class="to"><dt>{{ __('ขอแก้ไขเป็น') }}</dt><dd>{{ $ticket->requested_value }}</dd></div>
      </dl>
    @endif
  </section>

  <ol class="thread">
    @foreach($messages as $m)
      <li class="msg {{ $m->fromWorker() ? 'me' : 'them' }}">
        <div class="bubble">
          <div class="who">{{ $m->fromWorker() ? __('คุณ') : __('เจ้าหน้าที่') }} · <time>{{ $fmt($m->created_at) }}</time></div>
          @if($m->body)
            @if(isset($translations[$m->id]))
              <p class="body">{{ $translations[$m->id] }}</p>
              <details class="orig"><summary>{{ __('ดูข้อความต้นฉบับ') }}</summary><p>{{ $m->body }}</p></details>
            @else
              <p class="body">{{ $m->body }}</p>
            @endif
          @endif
          @if($m->attachments->isNotEmpty())
            <div class="thumbs">
              @foreach($m->attachments as $a)
                <a href="{{ route('foreign.tickets.attachment', [$ticket, $a]) }}" target="_blank" rel="noopener" title="{{ $a->original_name }}">
                  @if(in_array($a->mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true))
                    <img src="{{ route('foreign.tickets.attachment', [$ticket, $a]) }}" alt="{{ $a->original_name }}" loading="lazy">
                  @else
                    <span class="thumb-file">{{ strtoupper(pathinfo($a->original_name, PATHINFO_EXTENSION)) ?: 'IMG' }}</span>
                  @endif
                </a>
              @endforeach
            </div>
          @endif
        </div>
      </li>
    @endforeach
  </ol>

  @if($ticket->isClosed())
    <div class="alert info">{{ __('เรื่องนี้ปิดแล้ว หากมีปัญหาใหม่กรุณาแจ้งเรื่องใหม่') }}</div>
  @else
    <form method="post" action="{{ route('foreign.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="card form-card">
      @csrf
      @if($errors->any())
        <div class="alert bad" role="alert">{{ $errors->first() }}</div>
      @endif
      <div class="field">
        <label for="body">{{ __('ตอบกลับ') }}</label>
        <textarea class="input" id="body" name="body" rows="3" maxlength="3000" placeholder="{{ __('พิมพ์ข้อความ…') }}">{{ old('body') }}</textarea>
      </div>
      <div class="field"><x-image-upload /></div>
      <button class="btn block" type="submit">{{ __('ส่งข้อความ') }}</button>
    </form>
  @endif
</div>
</x-layout>
