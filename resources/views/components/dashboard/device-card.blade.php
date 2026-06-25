@props(['device', 'isActive' => true, 'disconnectRoute', 'purgeRoute'])

<x-dashboard.card class="p-5 flex flex-col gap-4">
    <div class="flex justify-between items-start">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded bg-[var(--color-surface-muted)] border border-[var(--color-border)] flex items-center justify-center text-xs font-bold uppercase text-[var(--color-text)]">
                {{ substr($device->name, 0, 1) }}
            </div>
            <div>
                <h3 class="text-base font-bold text-[var(--color-text)] leading-tight m-0">{{ $device->name }}</h3>
                <div class="text-[var(--color-muted)] text-xs mt-1">
                    {{ $isActive ? 'Active' : 'Disconnected' }} &middot; {{ $device->updated_at->diffForHumans() }}
                </div>
            </div>
        </div>
        @if($isActive)
            <x-dashboard.badge variant="accent">Active</x-dashboard.badge>
        @else
            <x-dashboard.badge variant="neutral">Disconnected</x-dashboard.badge>
        @endif
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-2 text-xs">
        <div class="bg-[var(--color-surface-muted)] rounded p-2 border border-[var(--color-border)]">
            <span class="text-[var(--color-muted)] block mb-0.5">Bookmarks</span>
            <strong class="text-[var(--color-text)] text-sm">{{ $device->bookmarks_count ?? 0 }}</strong>
        </div>
        <div class="bg-[var(--color-surface-muted)] rounded p-2 border border-[var(--color-border)]">
            <span class="text-[var(--color-muted)] block mb-0.5">History</span>
            <strong class="text-[var(--color-text)] text-sm">{{ $device->history_count ?? 0 }}</strong>
        </div>
        <div class="bg-[var(--color-surface-muted)] rounded p-2 border border-[var(--color-border)]">
            <span class="text-[var(--color-muted)] block mb-0.5">Tab snapshots</span>
            <strong class="text-[var(--color-text)] text-sm">{{ $device->tab_snapshots_count ?? 0 }}</strong>
        </div>
        <div class="bg-[var(--color-surface-muted)] rounded p-2 border border-[var(--color-border)]">
            <span class="text-[var(--color-muted)] block mb-0.5">Pending tabs</span>
            <strong class="text-[var(--color-text)] text-sm">{{ $device->pending_tab_commands_count ?? 0 }}</strong>
        </div>
    </div>

    <details class="group bg-[var(--color-surface-muted)] border border-[var(--color-border)] rounded-[var(--radius-md)] mt-1">
        <summary class="cursor-pointer text-xs font-semibold text-[var(--color-muted)] p-3 flex items-center gap-2 select-none hover:bg-[var(--color-border)] hover:text-[var(--color-text)] transition-colors rounded-[var(--radius-md)] group-open:rounded-b-none group-open:border-b group-open:border-[var(--color-border)] list-none [&::-webkit-details-marker]:hidden">
            Advanced details
        </summary>
        <div class="p-3 text-xs flex flex-col gap-3">
            <div>
                <div class="font-bold text-[var(--color-text)] mb-1">Device ID</div>
                <div class="font-mono text-[var(--color-muted)]">{{ $device->id }}</div>
            </div>
            
            @if($device->capabilities())
                <div>
                    <div class="font-bold text-[var(--color-text)] mb-1">What this browser can do</div>
                    <div class="flex flex-col gap-1.5 mt-2">
                        <div class="flex justify-between items-center">
                            <span class="text-[var(--color-muted)]">Can sync native bookmarks</span>
                            <x-dashboard.badge variant="{{ $device->capabilities()['bookmarks_read'] ? 'accent' : 'warning' }}">
                                {{ $device->capabilities()['bookmarks_read'] ? 'Available' : 'Blocked by Safari' }}
                            </x-dashboard.badge>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[var(--color-muted)]">Can sync native history</span>
                            <x-dashboard.badge variant="{{ $device->capabilities()['history_read'] ? 'accent' : ($device->capabilities()['history_mode'] === 'activity' ? 'warning' : 'danger') }}">
                                {{ $device->capabilities()['history_read'] ? 'Available' : ($device->capabilities()['history_mode'] === 'activity' ? 'Activity only' : 'Blocked by Safari') }}
                            </x-dashboard.badge>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-2 pt-3 border-t border-[var(--color-border)] flex flex-col gap-2">
                @if($isActive)
                    <form method="POST" action="{{ $disconnectRoute }}" onsubmit="return confirm('Disconnect this device? It will disappear from active devices and pending tab commands will be cancelled. Existing synced data will be kept.');">
                        @csrf
                        @method('DELETE')
                        <x-dashboard.button type="submit" variant="danger" class="w-full text-xs py-1 min-h-[30px]">Disconnect safely</x-dashboard.button>
                    </form>
                @else
                    <form method="POST" action="{{ $purgeRoute }}" onsubmit="return prompt('Type DELETE DEVICE DATA to confirm.') === 'DELETE DEVICE DATA';">
                        @csrf
                        @method('DELETE')
                        <x-dashboard.button type="submit" variant="danger" class="w-full text-xs py-1 min-h-[30px]">Purge permanently</x-dashboard.button>
                    </form>
                @endif
            </div>
        </div>
    </details>
</x-dashboard.card>
