<?php

namespace App\Services;

use App\Models\Service;

/**
 * Sugere a próxima porta livre dentro da faixa configurada, considerando
 * tanto as portas já cadastradas quanto as que estão em escuta no sistema.
 */
class PortAllocator
{
    public function __construct(protected ProcessManager $manager)
    {
    }

    public function nextFree(?int $ignoreServiceId = null): ?int
    {
        $start = (int) config('services_manager.port_range.start', 8000);
        $end = (int) config('services_manager.port_range.end', 8099);

        $taken = Service::query()
            ->whereNotNull('port')
            ->when($ignoreServiceId, fn ($query) => $query->whereKeyNot($ignoreServiceId))
            ->pluck('port')
            ->map(fn ($port): int => (int) $port)
            ->all();

        $listening = $this->manager->probe([])['ports'];

        for ($port = $start; $port <= $end; $port++) {
            if (! in_array($port, $taken, true) && ! in_array($port, $listening, true)) {
                return $port;
            }
        }

        return null;
    }
}
