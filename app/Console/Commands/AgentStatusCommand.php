<?php

namespace App\Console\Commands;

use App\Services\AgentInstaller;
use App\Services\AgentState;
use Illuminate\Console\Command;

class AgentStatusCommand extends Command
{
    protected $signature = 'agent:status';

    protected $description = 'Mostra a situação do agente de supervisão (registro no Windows e heartbeat)';

    public function handle(AgentInstaller $installer): int
    {
        $status = $installer->status();

        $this->table(['Item', 'Valor'], [
            ['Registrado', $status['installed'] ? 'sim' : 'não'],
            ['Método', $status['method'] ?? '—'],
            ['Estado', $status['state'] ?? '—'],
            ['Detalhe', $status['detail'] ?? '—'],
            ['Heartbeat', $status['heartbeat'] ? 'ativo' : 'parado'],
            ['Último heartbeat', AgentState::lastHeartbeat()?->format('d/m/Y H:i:s') ?? '—'],
            ['Terminal elevado', $status['elevated'] ? 'sim' : 'não'],
        ]);

        if (! $status['installed']) {
            $this->newLine();
            $this->line('Para registrar: php artisan agent:install');
        }

        return self::SUCCESS;
    }
}
