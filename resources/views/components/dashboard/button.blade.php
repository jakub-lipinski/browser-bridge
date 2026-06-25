@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-bold rounded-[var(--radius-sm)] transition-all min-h-[36px] outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2';
    
    $variants = [
        'primary' => 'bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-strong)] border border-[var(--color-primary)] hover:border-[var(--color-primary-strong)] shadow-sm',
        'secondary' => 'bg-[var(--color-surface)] text-[var(--color-text)] hover:bg-[var(--color-surface-muted)] border border-[var(--color-border-strong)] shadow-sm',
        'danger' => 'bg-[var(--color-danger)] text-white hover:bg-[#dc2626] border border-[var(--color-danger)] shadow-sm',
        'ghost' => 'bg-transparent text-[var(--color-muted)] hover:text-[var(--color-text)] hover:bg-[var(--color-surface-muted)] border border-transparent',
    ];
    
    $classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
