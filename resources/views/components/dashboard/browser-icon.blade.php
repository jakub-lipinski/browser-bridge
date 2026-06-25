@props(['browser' => 'unknown', 'class' => 'w-4 h-4'])

@php
    $normalized = strtolower($browser);
@endphp

@if ($normalized === 'chrome')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <circle cx="12" cy="12" r="10"/>
        <circle cx="12" cy="12" r="4"/>
        <line x1="21.17" y1="8" x2="12" y2="8"/>
        <line x1="3.95" y1="6.06" x2="8.54" y2="14"/>
        <line x1="10.88" y1="21.94" x2="15.46" y2="14"/>
    </svg>
@elseif ($normalized === 'safari')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <circle cx="12" cy="12" r="10"/>
        <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
    </svg>
@elseif (in_array($normalized, ['firefox', 'mozilla']))
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10c5.52 0 10-4.48 10-10S17.52 2 12 2z"/>
        <path d="M12 22c-5.52 0-10-4.48-10-10S6.48 2 12 2"/>
        <path d="M15.5 6.5A7.5 7.5 0 0 0 12 2v20a10 10 0 0 0 3.5-15.5z"/>
    </svg>
@elseif (in_array($normalized, ['edge', 'msedge']))
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <path d="M3.5 12a8.5 8.5 0 0 1 14.5-5.5c-.5 6.5-4.5 11.5-11 11.5"/>
        <path d="M7 18c3.5 0 6.5-2 8.5-5"/>
    </svg>
@else
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $class }}">
        <circle cx="12" cy="12" r="10"/>
        <line x1="2" y1="12" x2="22" y2="12"/>
        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
    </svg>
@endif
