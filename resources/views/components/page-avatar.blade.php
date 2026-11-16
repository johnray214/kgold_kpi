@props(['id', 'name' => '', 'size' => 'size-9'])
@php
    $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'PG';
    $avatarSrc = $id ? kpi_page_avatar((string) $id) : '';
@endphp
<span {{ $attributes->merge(['class' => "{$size} relative inline-grid place-items-center rounded-lg bg-primary-soft text-primary font-bold text-xs overflow-hidden shrink-0 ring-1 ring-slate-200/80"]) }}>
    <span aria-hidden="true">{{ $initials }}</span>
    @if ($avatarSrc !== '')
        <img src="{{ $avatarSrc }}"
             alt="{{ $name ?: 'Page' }} avatar"
             width="48" height="48"
             loading="lazy"
             decoding="async"
             onerror="this.remove()"
             class="absolute inset-0 size-full object-cover bg-white">
    @endif
</span>
