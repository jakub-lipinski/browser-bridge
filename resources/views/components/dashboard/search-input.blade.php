@props(['placeholder' => 'Search...', 'id' => null])

<div class="relative w-full">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[var(--color-muted)] opacity-70">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
    </div>
    <input 
        @if($id) id="{{ $id }}" @endif
        type="search" 
        {{ $attributes->merge(['class' => 'w-full bg-[var(--color-surface)] border border-[var(--color-border-strong)] text-[var(--color-text)] text-sm rounded-[var(--radius-md)] pl-9 pr-4 py-2 min-h-[38px] outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:border-transparent transition-shadow placeholder:text-[var(--color-muted)] placeholder:opacity-70']) }} 
        placeholder="{{ $placeholder }}"
    >
</div>
