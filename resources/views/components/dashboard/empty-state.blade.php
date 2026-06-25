@props(['icon' => null, 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center p-8 sm:p-12 border border-dashed border-[var(--color-border-strong)] rounded-[var(--radius-lg)] bg-[var(--color-surface-muted)]']) }}>
    @if($icon)
        <div class="text-[var(--color-muted)] opacity-50 mb-4 w-10 h-10 flex items-center justify-center">
            {!! $icon !!}
        </div>
    @endif
    <h3 class="text-sm font-bold text-[var(--color-text)] mb-1">{{ $title }}</h3>
    @if($description)
        <p class="text-sm text-[var(--color-muted)] max-w-sm mx-auto">{{ $description }}</p>
    @endif
    @if(isset($action))
        <div class="mt-5">
            {{ $action }}
        </div>
    @endif
</div>
