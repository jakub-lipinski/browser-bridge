@props(['label', 'value', 'meta' => null, 'icon' => null])

<div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-[var(--radius-lg)] shadow-[var(--shadow-sm)] p-5">
    <div class="flex items-center gap-3 mb-3">
        @if($icon)
            <div class="flex items-center justify-center w-8 h-8 rounded-md bg-[var(--color-surface-muted)] border border-[var(--color-border)] text-[var(--color-primary)]">
                {!! $icon !!}
            </div>
        @endif
        <h3 class="text-[var(--color-muted)] text-sm font-semibold m-0">{{ $label }}</h3>
    </div>
    <div class="text-3xl font-bold leading-none text-[var(--color-text)]">{{ $value }}</div>
    @if($meta)
        <div class="text-[var(--color-muted)] text-xs mt-2">{{ $meta }}</div>
    @endif
</div>
