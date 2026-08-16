<x-filament-panels::page>
    @php($capabilities = $this->getCapabilities())
    @php($groups = $this->getGroupedCommands())

    <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('commands.current_directory') }}</p>
        <p class="mt-1 break-all font-mono text-sm">{{ $this->directory ?: __('commands.no_directory') }}</p>

        <div class="mt-2 flex flex-wrap gap-1.5">
            @forelse ($capabilities as $capability)
                <span class="rounded-md bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-500">
                    {{ __('commands.requires.' . $capability) }}
                </span>
            @empty
                <span class="rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-500">
                    {{ __('commands.unknown_project') }}
                </span>
            @endforelse
        </div>
    </div>

    @if ($groups === [])
        <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
            {{ __('commands.empty') }}
        </div>
    @endif

    @foreach ($groups as $group => $items)
        <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-base font-semibold">{{ __('commands.groups.' . $group) }}</h2>

            <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($items as $item)
                    <button
                        type="button"
                        wire:click="run('{{ $item['key'] }}')"
                        wire:loading.attr="disabled"
                        wire:target="run"
                        @if ($item['danger'])
                            wire:confirm="{{ __('commands.confirm', ['command' => $item['command']]) }}"
                        @endif
                        @class([
                            'flex flex-col gap-1 rounded-lg border p-3 text-left transition',
                            'hover:border-green-500/60 hover:bg-green-500/5' => ! $item['danger'],
                            'border-red-500/40 hover:border-red-500/70 hover:bg-red-500/5' => $item['danger'],
                            'border-gray-200 dark:border-white/10' => ! $item['danger'],
                            'disabled:cursor-not-allowed disabled:opacity-50',
                        ])
                    >
                        <span class="flex items-center gap-2 text-sm font-medium">
                            {{ $item['label'] }}
                            @if ($item['danger'])
                                <span class="rounded bg-red-500/15 px-1.5 py-0.5 text-[10px] uppercase text-red-500">
                                    {{ __('commands.dangerous') }}
                                </span>
                            @endif
                        </span>
                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item['command'] }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $item['description'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endforeach

    <div wire:loading wire:target="run" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-white/10 dark:bg-gray-900">
        <span class="size-2 animate-pulse rounded-full bg-green-500"></span>
        {{ __('commands.running') }}
    </div>

    @if (filled($this->output))
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-gray-900" wire:loading.remove wire:target="run">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <h3 class="flex items-center gap-2 font-mono text-sm">
                    <span @class([
                        'size-2 rounded-full',
                        'bg-green-500' => $this->lastOk,
                        'bg-red-500' => ! $this->lastOk,
                    ])></span>
                    {{ $this->lastCommand }}
                </h3>
                <span class="text-xs text-gray-400 dark:text-gray-500">
                    {{ __('commands.duration', ['seconds' => $this->lastDuration ?? 0]) }}
                </span>
            </div>

            <pre class="hk-console">{{ $this->output }}</pre>
        </div>
    @endif
</x-filament-panels::page>
