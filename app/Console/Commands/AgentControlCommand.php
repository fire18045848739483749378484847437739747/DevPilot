<?php

namespace App\Console\Commands;

use App\Services\AgentInstaller;
use Illuminate\Console\Command;

class AgentControlCommand extends Command
{
    protected $signature = 'agent:control {action : start ou stop}';

    protected $description = 'Inicia ou para o agente já registrado no Windows';

    public function handle(AgentInstaller $installer): int
    {
        $action = (string) $this->argument('action');

        if (! in_array($action, ['start', 'stop'], true)) {
            $this->error('Ação inválida. Use "start" ou "stop".');

            return self::FAILURE;
        }

        $result = $action === 'start' ? $installer->start() : $installer->stop();

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        if (isset($result['command'])) {
            $this->newLine();
            $this->line('Rode em um terminal como Administrador:');
            $this->line('  ' . $result['command']);
        }

        return self::FAILURE;
    }
}
