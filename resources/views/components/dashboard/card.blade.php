<div {{ $attributes->merge(['class' => 'bg-[var(--color-surface)] border border-[var(--color-border)] rounded-[var(--radius-lg)] shadow-[var(--shadow-sm)] overflow-hidden']) }}>
    {{ $slot }}
</div>
