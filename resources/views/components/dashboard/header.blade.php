@props(['kicker' => null, 'title', 'description' => null, 'actions' => null])

<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-5 pb-6 mb-6 border-b border-[var(--color-border)]">
    <div>
        @if($kicker)
            <p class="text-[var(--color-primary)] text-sm font-bold mb-2 uppercase tracking-wide">{{ $kicker }}</p>
        @endif
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-[var(--color-text)] mb-2">{{ $title }}</h1>
        @if($description)
            <p class="text-[var(--color-muted)] text-base max-w-2xl">{{ $description }}</p>
        @endif
    </div>
    @if($actions)
        <div class="flex flex-wrap items-center gap-3 shrink-0">
            {{ $actions }}
        </div>
    @endif
</header>
