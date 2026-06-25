@props(['platform' => 'unknown', 'class' => 'w-4 h-4'])

@php
    $normalized = strtolower($platform);
@endphp

@if (in_array($normalized, ['macos', 'mac', 'darwin']))
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <rect x="2" y="3" width="20" height="14" rx="2"/>
        <line x1="8" y1="21" x2="16" y2="21"/>
        <line x1="12" y1="17" x2="12" y2="21"/>
    </svg>
@elseif ($normalized === 'windows')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <rect x="3" y="3" width="8" height="8"/>
        <rect x="13" y="3" width="8" height="8"/>
        <rect x="3" y="13" width="8" height="8"/>
        <rect x="13" y="13" width="8" height="8"/>
    </svg>
@elseif ($normalized === 'linux')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Z"/>
        <path d="m12 14-4 4"/>
        <path d="m16 14-4 4"/>
    </svg>
@elseif (in_array($normalized, ['ios', 'android']))
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <rect x="5" y="2" width="14" height="20" rx="2" ry="2"/>
        <line x1="12" y1="18" x2="12.01" y2="18"/>
    </svg>
@else
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <rect x="2" y="3" width="20" height="14" rx="2"/>
        <line x1="8" y1="21" x2="16" y2="21"/>
        <line x1="12" y1="17" x2="12" y2="21"/>
    </svg>
@endif
