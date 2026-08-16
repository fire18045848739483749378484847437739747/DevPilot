<x-filament-panels::page>
    @php($status = $this->agentStatus ?? [])

    <div class="grid gap-4 md:grid-cols-3">
        <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('agent.cards.registration') }}</p>
            <p @class([
                'mt-1 text-lg font-semibold',
                'text-green-500' => data_get($status, 'installed'),
                'text-red-500' => ! data_get($status, 'installed'),
            ])>
                {{ data_get($status, 'installed') ? __('agent.cards.registered') : __('agent.cards.not_registered') }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ data_get($status, 'method') ? __('agent.methods.' . data_get($status, 'method')) : __('agent.cards.no_method') }}
            </p>
        </div>

        <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('agent.cards.state') }}</p>
            <p class="mt-1 text-lg font-semibold">{{ data_get($status, 'state') ?: '—' }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ data_get($status, 'detail') ?: '—' }}</p>
        </div>

        <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('agent.cards.heartbeat') }}</p>
            <p @class([
                'mt-1 text-lg font-semibold',
                'text-green-500' => data_get($status, 'heartbeat'),
                'text-red-500' => ! data_get($status, 'heartbeat'),
            ])>
                {{ data_get($status, 'heartbeat') ? __('agent.cards.alive') : __('agent.cards.dead') }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ $this->getLastHeartbeat() ?? __('agent.cards.never') }}
            </p>
        </div>
    </div>

    @unless (data_get($status, 'elevated'))
        <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
            <p class="font-medium">{{ __('agent.elevation.heading') }}</p>
            <p class="mt-1 text-gray-600 dark:text-gray-300">{{ __('agent.elevation.body') }}</p>
            <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-950/80 p-3 font-mono text-xs text-green-400">{{ app(\App\Services\AgentInstaller::class)->manualCommand() }}</pre>
        </div>
    @endunless

    <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
        <h2 class="text-base font-semibold">{{ __('agent.details.heading') }}</h2>
        <dl class="mt-3 grid gap-2 text-sm">
            @foreach ($this->getInstallerDetails() as $label => $value)
                <div class="flex flex-col gap-1 border-b border-gray-100 pb-2 last:border-0 dark:border-white/5 md:flex-row md:items-center md:gap-4">
                    <dt class="w-48 shrink-0 text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="break-all font-mono text-xs">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</x-filament-panels::page>
