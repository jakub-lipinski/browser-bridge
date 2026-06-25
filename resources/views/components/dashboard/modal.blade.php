@props(['id', 'title', 'description' => null])

<dialog id="{{ $id }}" class="fixed inset-0 z-50 bg-transparent p-4 sm:p-6 lg:p-8 m-0 w-full h-full max-w-none max-h-none flex items-center justify-center bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-200 [&[open]]:opacity-100 [&[open]]:pointer-events-auto">
    <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-[var(--radius-lg)] shadow-2xl w-full max-w-md flex flex-col max-h-[90vh] overflow-hidden transform scale-95 transition-transform duration-200 [[open]_&]:scale-100">
        <div class="px-6 py-5 border-b border-[var(--color-border)]">
            <h2 class="text-lg font-bold text-[var(--color-text)] m-0 leading-tight">{{ $title }}</h2>
            @if($description)
                <p class="text-sm text-[var(--color-muted)] mt-1.5">{{ $description }}</p>
            @endif
        </div>
        <div class="px-6 py-5 overflow-y-auto flex-1">
            {{ $slot }}
        </div>
        @if(isset($footer))
            <div class="px-6 py-4 border-t border-[var(--color-border)] bg-[var(--color-surface-muted)] flex justify-end gap-3 rounded-b-[var(--radius-lg)]">
                {{ $footer }}
            </div>
        @endif
    </div>
</dialog>

<script>
    // Helper to open/close modals
    window.openModal = function(id) {
        document.getElementById(id).showModal();
        document.body.style.overflow = 'hidden';
    };
    window.closeModal = function(id) {
        document.getElementById(id).close();
        document.body.style.overflow = '';
    };
</script>
