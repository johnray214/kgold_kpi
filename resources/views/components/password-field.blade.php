@props([
    'name',
    'id' => null,
    'placeholder' => '••••••••',
    'required' => false,
    'autocomplete' => 'off',
])
@php
    $inputId = $id ?? 'pw-'.\Illuminate\Support\Str::random(6);
@endphp
<div class="relative" data-pw-wrap>
    <input id="{{ $inputId }}"
           type="password"
           name="{{ $name }}"
           placeholder="{{ $placeholder }}"
           autocomplete="{{ $autocomplete }}"
           @required($required)
           {{ $attributes->merge(['class' => 'field w-full pr-9']) }}>
    <button type="button"
            data-pw-toggle="{{ $inputId }}"
            aria-label="Show or hide password"
            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-lg text-slate-400 hover:text-ink cursor-pointer transition-colors">
        <x-icon name="eye" data-eye-open class="size-4"/>
        <x-icon name="eye-off" data-eye-closed class="size-4 hidden"/>
    </button>
</div>
