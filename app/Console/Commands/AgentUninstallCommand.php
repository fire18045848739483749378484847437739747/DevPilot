<?php

namespace App\Console\Commands;

use App\Services\AgentInstaller;
use Illuminate\Console\Command;

class AgentUninstallCommand extends Command
{
    protected $signature = 'agent:uninstall';

    protected $description = 'Remove o registro do agente de supervisão no Windows';

    public function handle(AgentInstaller $installer): int
    {
        $result = $installer->uninstall();

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
