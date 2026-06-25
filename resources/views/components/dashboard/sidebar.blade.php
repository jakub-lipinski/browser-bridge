<aside class="w-full md:w-56 shrink-0 md:sticky md:top-8 self-start flex flex-col gap-6">
    <div class="flex items-center gap-3 px-2">
        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-[var(--color-primary-subtle)] text-[var(--color-primary)] border border-[var(--color-primary-border)] shadow-sm">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
        </div>
        <span class="font-bold text-lg tracking-tight">BrowserBridge</span>
    </div>

    <nav class="flex flex-col gap-1.5">
        {{ $slot }}
    </nav>
</aside>
