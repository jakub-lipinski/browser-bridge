@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'bg-[var(--color-danger-bg)] border border-[var(--color-danger-border)] rounded-[var(--radius-lg)] p-5']) }}>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-base font-bold text-[var(--color-danger-text)] m-0">{{ $title }}</h3>
            @if($description)
                <p class="text-sm text-[var(--color-danger-text)] opacity-90 mt-1">{{ $description }}</p>
            @endif
        </div>
        <div class="shrink-0">
            {{ $slot }}
        </div>
    </div>
</div>
