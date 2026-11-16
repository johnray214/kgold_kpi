@props(['size' => 'size-8', 'iconSize' => '20'])

<span {{ $attributes->merge(['class' => "$size rounded-xl bg-linear-to-br from-[#4B55F5] to-[#3730A3] text-white grid place-items-center shadow-[0_5px_14px_rgba(75,85,245,0.35)] shrink-0"]) }}>
    <svg width="{{ $iconSize }}" height="{{ $iconSize }}" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <rect x="4" y="13.5" width="3.2" height="6.5" rx="1.5" fill="#C7D2FE"/>
        <rect x="10.4" y="9.5" width="3.2" height="10.5" rx="1.5" fill="#E0E7FF"/>
        <rect x="16.8" y="5.5" width="3.2" height="14.5" rx="1.5" fill="#FFFFFF"/>
        <path d="M5.6 10.5L12 7L18.4 4" stroke="#34D399" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="18.4" cy="4" r="1.8" fill="#34D399"/>
    </svg>
</span>
