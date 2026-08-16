<x-filament-widgets::widget wire:poll.3s>
    <div class="fi-wi-service-logs overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-white/10">
            <h3 class="flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                <span class="size-2 animate-pulse rounded-full bg-green-500"></span>
                {{ __('widgets.live_logs') }}
            </h3>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                {{ __('widgets.last_lines', ['lines' => 300]) }}
            </span>
        </div>

        @php($logs = $this->getLogs())

        <pre class="hk-console">@forelse (preg_split('/\r\n|\r|\n/', $logs) as $line)<span @class([
            'hk-console-line-stderr' => \Illuminate\Support\Str::contains($line, ['ERROR', 'Fatal', 'Exception', 'error]']),
            'hk-console-line-system' => \Illuminate\Support\Str::contains($line, ['INFO', 'Server running']),
        ])>{{ $line }}</span>
@empty
<span class="hk-console-empty">{{ __('services.messages.no_logs') }}</span>@endforelse</pre>
    </div>
</x-filament-widgets::widget>
