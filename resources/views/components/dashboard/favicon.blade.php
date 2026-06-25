@props(['url', 'class' => 'w-6 h-6'])

@php
    $domain = parse_url($url, PHP_URL_HOST) ?? '';
    $faviconUrl = $domain ? "https://www.google.com/s2/favicons?domain={$domain}&sz=32" : null;
    $firstLetter = $domain ? strtoupper(substr(str_replace('www.', '', $domain), 0, 1)) : '?';
@endphp

@if ($faviconUrl)
    <img src="{{ $faviconUrl }}" alt="{{ $domain }}" loading="lazy" {{ $attributes->merge(['class' => $class . ' shrink-0 object-contain rounded bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-0.5']) }} onerror="this.outerHTML='<div class=\'{{ $class }} shrink-0 rounded bg-[var(--color-surface-muted)] border border-[var(--color-border)] flex items-center justify-center text-xs font-bold text-[var(--color-muted)] uppercase\'>{{ $firstLetter }}</div>'" />
@else
    <div {{ $attributes->merge(['class' => $class . ' shrink-0 rounded bg-[var(--color-surface-muted)] border border-[var(--color-border)] flex items-center justify-center text-xs font-bold text-[var(--color-muted)] uppercase']) }}>
        {{ $firstLetter }}
    </div>
@endif
