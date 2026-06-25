@props(['href', 'active' => false, 'icon' => null])

<a href="{{ $href }}" data-sidebar-item @class([
    'flex items-center gap-2.5 px-3 py-2 text-sm font-semibold rounded-[var(--radius-sm)] transition-colors',
    'active bg-[var(--color-surface)] text-[var(--color-text)] shadow-[var(--shadow-sm)]' => $active,
    'text-[var(--color-muted)] hover:bg-[var(--color-surface)] hover:text-[var(--color-text)] hover:shadow-[var(--shadow-sm)]' => !$active,
])>
    @if($icon)
        <span class="w-4 h-4 flex items-center justify-center opacity-70">
            {!! $icon !!}
        </span>
    @endif
    {{ $slot }}
</a>
