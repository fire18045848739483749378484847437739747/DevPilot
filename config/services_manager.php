<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Raízes de desenvolvimento
    |--------------------------------------------------------------------------
    |
    | Diretórios que o seletor de pastas do formulário pode navegar. Serve como
    | limite de segurança: o navegador de diretórios nunca lista nada fora
    | destas raízes.
    |
    */

    'dev_roots' => array_values(array_filter(
        explode(',', (string) env('DEV_ROOTS', 'C:\laragon\www'))
    )),

    /*
    |--------------------------------------------------------------------------
    | Portas sugeridas
    |--------------------------------------------------------------------------
    |
    | Faixa usada para sugerir a próxima porta livre ao cadastrar um serviço.
    |
    */

    'port_range' => [
        'start' => (int) env('SERVICE_PORT_START', 8000),
        'end' => (int) env('SERVICE_PORT_END', 8099),
    ],

    /*
    |--------------------------------------------------------------------------
    | Host padrão dos serviços
    |--------------------------------------------------------------------------
    |
    | Usado ao sugerir o comando de um projeto detectado. `127.0.0.1` só aceita
    | acesso da própria máquina; o IP da rede local (detectável no formulário)
    | libera o acesso de outros aparelhos; `0.0.0.0` escuta em tudo.
    |
    */

    'default_host' => env('SERVICE_DEFAULT_HOST', '127.0.0.1'),

    /*
    |--------------------------------------------------------------------------
    | Comandos prontos do projeto
    |--------------------------------------------------------------------------
    |
    | Catálogo executado na pasta do projeto (o "diretório de execução" do
    | serviço) pela página "Comandos". São comandos de uma tacada só — quem
    | roda em loop é serviço, não comando.
    |
    | `requires` limita o comando ao tipo de projeto (laravel|filament|node|
    | composer); `danger` exige confirmação; `timeout` sobrescreve o padrão.
    | Os rótulos saem de `lang/pt_BR/commands.php`, e podem ser sobrescritos
    | aqui com as chaves `label` e `description`.
    |
    */

    'commands' => [
        'timeout' => (int) env('PROJECT_COMMAND_TIMEOUT', 300),

        'catalog' => [
            // Diagnóstico
            'about' => ['group' => 'info', 'requires' => 'laravel', 'command' => 'php artisan about'],
            'route-list' => ['group' => 'info', 'requires' => 'laravel', 'command' => 'php artisan route:list'],
            'db-show' => ['group' => 'info', 'requires' => 'laravel', 'command' => 'php artisan db:show'],
            'migrate-status' => ['group' => 'info', 'requires' => 'laravel', 'command' => 'php artisan migrate:status'],
            'filament-about' => ['group' => 'info', 'requires' => 'filament', 'command' => 'php artisan filament:about'],

            // Cache
            'optimize-clear' => ['group' => 'cache', 'requires' => 'laravel', 'command' => 'php artisan optimize:clear'],
            'optimize' => ['group' => 'cache', 'requires' => 'laravel', 'command' => 'php artisan optimize'],
            'config-clear' => ['group' => 'cache', 'requires' => 'laravel', 'command' => 'php artisan config:clear'],
            'route-clear' => ['group' => 'cache', 'requires' => 'laravel', 'command' => 'php artisan route:clear'],
            'view-clear' => ['group' => 'cache', 'requires' => 'laravel', 'command' => 'php artisan view:clear'],
            'cache-clear' => ['group' => 'cache', 'requires' => 'laravel', 'command' => 'php artisan cache:clear'],

            // Banco
            'migrate' => ['group' => 'database', 'requires' => 'laravel', 'command' => 'php artisan migrate --force'],
            'migrate-rollback' => ['group' => 'database', 'requires' => 'laravel', 'command' => 'php artisan migrate:rollback --force', 'danger' => true],
            'migrate-fresh-seed' => ['group' => 'database', 'requires' => 'laravel', 'command' => 'php artisan migrate:fresh --force --seed', 'danger' => true],
            'db-seed' => ['group' => 'database', 'requires' => 'laravel', 'command' => 'php artisan db:seed --force', 'danger' => true],

            // Fila
            'queue-restart' => ['group' => 'queue', 'requires' => 'laravel', 'command' => 'php artisan queue:restart'],
            'queue-failed' => ['group' => 'queue', 'requires' => 'laravel', 'command' => 'php artisan queue:failed'],
            'queue-retry-all' => ['group' => 'queue', 'requires' => 'laravel', 'command' => 'php artisan queue:retry all'],
            'queue-flush' => ['group' => 'queue', 'requires' => 'laravel', 'command' => 'php artisan queue:flush', 'danger' => true],

            // Filament
            'filament-upgrade' => ['group' => 'filament', 'requires' => 'filament', 'command' => 'php artisan filament:upgrade'],
            'filament-optimize' => ['group' => 'filament', 'requires' => 'filament', 'command' => 'php artisan filament:optimize'],
            'filament-optimize-clear' => ['group' => 'filament', 'requires' => 'filament', 'command' => 'php artisan filament:optimize-clear'],
            'filament-assets' => ['group' => 'filament', 'requires' => 'filament', 'command' => 'php artisan filament:assets'],
            'filament-cache-components' => ['group' => 'filament', 'requires' => 'filament', 'command' => 'php artisan filament:cache-components'],
            'filament-clear-cached-components' => ['group' => 'filament', 'requires' => 'filament', 'command' => 'php artisan filament:clear-cached-components'],

            // Build e dependências
            'storage-link' => ['group' => 'build', 'requires' => 'laravel', 'command' => 'php artisan storage:link'],
            'composer-install' => ['group' => 'build', 'requires' => 'composer', 'command' => 'composer install', 'timeout' => 900],
            'composer-dump' => ['group' => 'build', 'requires' => 'composer', 'command' => 'composer dump-autoload -o'],
            'npm-install' => ['group' => 'build', 'requires' => 'node', 'command' => 'npm install', 'timeout' => 900],
            'npm-build' => ['group' => 'build', 'requires' => 'node', 'command' => 'npm run build', 'timeout' => 900],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agente de supervisão
    |--------------------------------------------------------------------------
    |
    | Registro do agente como serviço do Windows. `task` usa o Agendador de
    | Tarefas (nativo, sem dependência externa); `nssm` usa o NSSM, que precisa
    | estar no PATH ou apontado por `nssm_path`.
    |
    */

    'agent' => [
        'task_name' => env('AGENT_TASK_NAME', 'GerenciadorDeProcessos-Agent'),
        'service_name' => env('AGENT_SERVICE_NAME', 'GerenciadorProcessosAgent'),
        'nssm_path' => env('NSSM_PATH', 'nssm'),
        'interval' => (int) env('AGENT_INTERVAL', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs dos serviços
    |--------------------------------------------------------------------------
    |
    | O stdout/stderr de cada serviço é redirecionado para arquivo pelo
    | PowerShell. O ingestor lê o que foi acrescentado desde a última leitura e
    | grava em `service_logs` (com nível), habilitando busca e filtro no painel.
    |
    */

    'logs' => [
        'ingest' => filter_var(env('SERVICE_LOGS_INGEST', true), FILTER_VALIDATE_BOOLEAN),

        // Máximo de bytes lidos por serviço a cada ciclo (evita travar o painel
        // quando um serviço despeja megabytes de saída de uma vez).
        'max_bytes_per_cycle' => (int) env('SERVICE_LOGS_MAX_BYTES', 262144),

        // Linhas maiores que isto são truncadas antes de ir para o banco.
        'max_line_length' => 2000,

        // Retenção por serviço na tabela `service_logs`.
        'max_rows_per_service' => (int) env('SERVICE_LOGS_MAX_ROWS', 5000),

        // Arquivo de log maior que isto é truncado (após a ingestão).
        'rotate_size_mb' => (int) env('SERVICE_LOGS_ROTATE_MB', 10),

        // Arquivos antigos e órfãos são apagados depois deste prazo.
        'retention_days' => (int) env('SERVICE_LOGS_RETENTION_DAYS', 7),

        // Intervalo mínimo entre varreduras de rotação.
        'rotate_interval_minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Health check HTTP
    |--------------------------------------------------------------------------
    |
    | Porta aberta não significa aplicação respondendo. Quando habilitado no
    | serviço, o painel/agente faz uma requisição HTTP e compara o status.
    |
    */

    'health' => [
        'interval' => (int) env('HEALTH_CHECK_INTERVAL', 30),

        // Falhas consecutivas antes de considerar o serviço "com falha".
        'failure_threshold' => (int) env('HEALTH_CHECK_FAILURES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alertas
    |--------------------------------------------------------------------------
    |
    | Notificações do painel (banco) quando um serviço cai, estoura o limite de
    | reinícios, excede CPU/memória ou falha no health check.
    |
    */

    'alerts' => [
        'enabled' => filter_var(env('SERVICE_ALERTS', true), FILTER_VALIDATE_BOOLEAN),

        // Janela em que o mesmo alerta do mesmo serviço não é repetido.
        'cooldown_minutes' => (int) env('SERVICE_ALERTS_COOLDOWN', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadados do projeto (página inicial pública)
    |--------------------------------------------------------------------------
    |
    | Usados na landing page em `/`. Ajuste o repositório e o autor antes de
    | publicar — o mesmo nome deve constar no arquivo LICENSE.
    |
    */

    'project' => [
        'name' => env('PROJECT_NAME', 'Gerenciador de Processos'),
        'tagline' => env(
            'PROJECT_TAGLINE',
            'Painel web para iniciar, parar e monitorar processos de desenvolvimento no Windows.'
        ),
        'repository' => env('PROJECT_REPOSITORY', ''),
        'author' => env('PROJECT_AUTHOR', 'EudesSA - ProezaTech'),
        'website' => env('PROJECT_WEBSITE', 'https://www.proezatech.com'),
        'license' => 'MIT',
        'version' => env('PROJECT_VERSION', '1.0.0'),
    ],

];

