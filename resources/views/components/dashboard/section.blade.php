@props(['title', 'description' => null, 'badge' => null])

<section {{ $attributes->merge(['class' => 'flex flex-col gap-4']) }}>
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-[var(--color-text)] tracking-tight m-0">{{ $title }}</h2>
            @if($description)
                <p class="text-sm text-[var(--color-muted)] mt-1.5">{{ $description }}</p>
            @endif
        </div>
        @if($badge)
            <div class="shrink-0">
                {!! $badge !!}
            </div>
        @endif
    </div>

    {{ $slot }}
</section>
