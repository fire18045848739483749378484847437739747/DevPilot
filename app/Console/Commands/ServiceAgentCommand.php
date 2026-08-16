<?php

namespace App\Console\Commands;

use App\Services\AgentState;
use App\Services\ServiceAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ServiceAgentCommand extends Command
{
    protected $signature = 'services:agent
        {--once : Executa um único ciclo de supervisão e encerra}
        {--interval=3 : Segundos entre ciclos de supervisão}';

    protected $description = 'Executa o agente de supervisão de serviços em segundo plano';

    public function handle(ServiceAgent $agent): int
    {
        if ($this->option('once')) {
            $agent->supervise(bootstrap: true);

            return self::SUCCESS;
        }

        $this->info('Agente de serviços iniciado (PID ' . getmypid() . ').');

        AgentState::register();

        try {
            $agent->supervise(bootstrap: true);
        } catch (Throwable $e) {
            Log::error('Falha no ciclo inicial do agente de serviços: ' . $e->getMessage(), ['exception' => $e]);
        }

        register_shutdown_function(fn () => AgentState::unregister());

        while (true) {
            sleep(max(1, (int) $this->option('interval')));

            try {
                $agent->supervise();
            } catch (Throwable $e) {
                Log::error('Falha no ciclo do agente de serviços: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }
}
