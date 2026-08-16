<?php

namespace App\Console\Commands;

use App\Services\AgentInstaller;
use Illuminate\Console\Command;

class AgentInstallCommand extends Command
{
    protected $signature = 'agent:install
        {--method=task : task (Agendador de Tarefas) ou nssm (serviço do Windows)}
        {--trigger=logon : logon (usuário atual, S4U) ou boot (SYSTEM, antes do login)}';

    protected $description = 'Registra o agente de supervisão para iniciar sozinho com o Windows';

    public function handle(AgentInstaller $installer): int
    {
        $method = (string) $this->option('method');
        $trigger = (string) $this->option('trigger');

        if (! in_array($method, [AgentInstaller::METHOD_TASK, AgentInstaller::METHOD_NSSM], true)) {
            $this->error('Método inválido. Use --method=task ou --method=nssm.');

            return self::FAILURE;
        }

        if (! in_array($trigger, [AgentInstaller::TRIGGER_LOGON, AgentInstaller::TRIGGER_BOOT], true)) {
            $this->error('Gatilho inválido. Use --trigger=logon ou --trigger=boot.');

            return self::FAILURE;
        }

        $this->line('PHP:      ' . $installer->phpBinary());
        $this->line('Projeto:  ' . $installer->projectPath());
        $this->line('Comando:  ' . $installer->agentArguments());
        $this->newLine();

        $result = $installer->install($method, $trigger);

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
