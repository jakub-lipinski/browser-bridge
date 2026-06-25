@props(['variant' => 'neutral'])

@php
    $baseClasses = 'inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-full whitespace-nowrap border';
    
    $variants = [
        'neutral' => 'bg-[var(--color-surface-muted)] text-[var(--color-muted)] border-[var(--color-border)]',
        'accent' => 'bg-[var(--color-primary-subtle)] text-[var(--color-primary-text)] border-[var(--color-primary-border)]',
        'warning' => 'bg-[var(--color-warning-bg)] text-[var(--color-warning-text)] border-[var(--color-warning-border)]',
        'danger' => 'bg-[var(--color-danger-bg)] text-[var(--color-danger-text)] border-[var(--color-danger-border)]',
    ];
    
    $classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['neutral']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
