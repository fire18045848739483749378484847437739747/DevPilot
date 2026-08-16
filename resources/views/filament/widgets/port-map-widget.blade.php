<x-filament-widgets::widget wire:poll.10s>
    <div class="fi-wi-port-map overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-white/10">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('widgets.port_map') }}
            </h3>

            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-green-500"></span>{{ __('widgets.port_map_service_up') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-amber-500"></span>{{ __('widgets.port_map_service_down') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-red-500"></span>{{ __('widgets.port_map_foreign') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-gray-300 dark:bg-gray-700"></span>{{ __('widgets.port_map_free') }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-4 gap-1.5 p-4 sm:grid-cols-8 lg:grid-cols-16">
            @foreach ($this->getPorts() as $entry)
                @php
                    $classes = match ($entry['state']) {
                        'service-up' => 'bg-green-500/15 text-green-600 ring-green-500/40 dark:text-green-400',
                        'service-down' => 'bg-amber-500/15 text-amber-600 ring-amber-500/40 dark:text-amber-400',
                        'foreign' => 'bg-red-500/15 text-red-600 ring-red-500/40 dark:text-red-400',
                        default => 'bg-gray-100 text-gray-400 ring-gray-200 dark:bg-white/5 dark:text-gray-600 dark:ring-white/10',
                    };

                    $title = $entry['service']?->name
                        ?? ($entry['state'] === 'foreign'
                            ? __('widgets.port_map_foreign')
                            : __('widgets.port_map_free'));
                @endphp

                @if ($entry['service'])
                    <a
                        href="{{ \App\Filament\Resources\Services\ServiceResource::getUrl('view', ['record' => $entry['service']]) }}"
                        title="{{ $title }}"
                        class="rounded-md px-1.5 py-1.5 text-center font-mono text-xs ring-1 transition hover:scale-105 {{ $classes }}"
                    >{{ $entry['port'] }}</a>
                @else
                    <span
                        title="{{ $title }}"
                        class="rounded-md px-1.5 py-1.5 text-center font-mono text-xs ring-1 {{ $classes }}"
                    >{{ $entry['port'] }}</span>
                @endif
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
