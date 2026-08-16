<?php

namespace App\Filament\Widgets;

use App\Models\Service;
use App\Services\ProcessManager;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Mapa das portas de desenvolvimento: mostra quais portas da faixa configurada
 * estão livres, ocupadas por um serviço do gerenciador, ou ocupadas por um
 * processo externo (conflito).
 */
class PortMapWidget extends Widget
{
    use CanPoll;

    protected string $view = 'filament.widgets.port-map-widget';

    protected int|string|array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        return '10s';
    }

    /**
     * @return Collection<int, array{port: int, state: string, service: ?Service}>
     */
    public function getPorts(): Collection
    {
        $start = (int) config('services_manager.port_range.start', 8000);
        $end = (int) config('services_manager.port_range.end', 8099);

        // Limita a faixa exibida para não gerar uma grade gigante.
        $end = min($end, $start + 63);

        $listening = app(ProcessManager::class)->probe([])['ports'];

        $services = Service::query()
            ->whereNotNull('port')
            ->get()
            ->keyBy('port');

        return collect(range($start, $end))->map(function (int $port) use ($listening, $services): array {
            $service = $services->get($port);
            $isListening = in_array($port, $listening, true);

            $state = match (true) {
                $service !== null && $isListening => 'service-up',
                $service !== null => 'service-down',
                $isListening => 'foreign',
                default => 'free',
            };

            return ['port' => $port, 'state' => $state, 'service' => $service];
        });
    }
}
