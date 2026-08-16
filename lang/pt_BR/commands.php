<?php

return [
    'navigation_label' => 'Comandos',
    'title' => 'Comandos do projeto',

    'current_directory' => 'Pasta onde os comandos serão executados',
    'no_directory' => 'Nenhuma pasta selecionada',
    'unknown_project' => 'Projeto não reconhecido',
    'empty' => 'Nenhum comando disponível para esta pasta. Escolha a raiz de um projeto (a pasta que tem o artisan ou o composer.json).',
    'running' => 'Executando...',
    'dangerous' => 'destrutivo',
    'duration' => ':seconds s',
    'confirm' => "Este comando altera dados de forma irreversível:\n\n:command\n\nDeseja continuar?",

    'groups' => [
        'info' => 'Diagnóstico',
        'cache' => 'Cache',
        'database' => 'Banco de dados',
        'queue' => 'Filas',
        'filament' => 'Filament',
        'build' => 'Dependências e build',
    ],

    'requires' => [
        'laravel' => 'Laravel',
        'filament' => 'Filament',
        'composer' => 'Composer',
        'node' => 'Node',
    ],

    'actions' => [
        'choose_directory' => 'Trocar projeto',
        'show_dangerous' => 'Mostrar destrutivos',
        'hide_dangerous' => 'Ocultar destrutivos',
        'clear_output' => 'Limpar saída',
    ],

    'messages' => [
        'unknown' => 'Comando desconhecido: :key',
        'invalid_directory' => 'Pasta inválida ou fora das raízes permitidas (DEV_ROOTS).',
        'not_applicable' => 'Este comando exige um projeto :requires.',
        'empty' => 'O comando está vazio.',
        'no_output' => '(sem saída)',
        'timed_out' => 'O comando excedeu o tempo limite de :seconds segundos e foi interrompido.',
        'executed' => 'Comando executado pelo painel: :command — :status em :duration s.',
        'success' => 'sucesso',
        'failure' => 'falha',
        'finished_ok' => 'Concluído: :command',
        'finished_error' => 'Falhou: :command',
    ],

    'items' => [
        'about' => [
            'label' => 'Informações do projeto',
            'description' => 'Versão do Laravel, PHP, ambiente, drivers de cache e fila.',
        ],
        'route-list' => [
            'label' => 'Listar rotas',
            'description' => 'Todas as rotas registradas, com método, URI e controller.',
        ],
        'db-show' => [
            'label' => 'Informações do banco',
            'description' => 'Conexão, tabelas e tamanho do banco em uso.',
        ],
        'migrate-status' => [
            'label' => 'Status das migrations',
            'description' => 'O que já rodou e o que está pendente.',
        ],
        'filament-about' => [
            'label' => 'Informações do Filament',
            'description' => 'Pacotes do Filament instalados e suas versões.',
        ],

        'optimize-clear' => [
            'label' => 'Limpar todos os caches',
            'description' => 'Config, rotas, views e eventos. É o primeiro socorro de "mudei e não refletiu".',
        ],
        'optimize' => [
            'label' => 'Otimizar',
            'description' => 'Gera os caches de config, rotas e views.',
        ],
        'config-clear' => [
            'label' => 'Limpar cache de config',
            'description' => 'Necessário depois de mexer no .env.',
        ],
        'route-clear' => [
            'label' => 'Limpar cache de rotas',
            'description' => 'Necessário depois de mexer nos arquivos de rotas.',
        ],
        'view-clear' => [
            'label' => 'Limpar views compiladas',
            'description' => 'Apaga o Blade compilado em storage/framework/views.',
        ],
        'cache-clear' => [
            'label' => 'Limpar cache da aplicação',
            'description' => 'Esvazia o store de cache configurado.',
        ],

        'migrate' => [
            'label' => 'Rodar migrations',
            'description' => 'Aplica as migrations pendentes (--force).',
        ],
        'migrate-rollback' => [
            'label' => 'Desfazer último lote',
            'description' => 'Reverte o último lote de migrations. Pode apagar dados.',
        ],
        'migrate-fresh-seed' => [
            'label' => 'Recriar banco com seed',
            'description' => 'Derruba todas as tabelas, roda tudo de novo e popula. Apaga todos os dados.',
        ],
        'db-seed' => [
            'label' => 'Rodar seeders',
            'description' => 'Executa os seeders. Pode duplicar registros se rodar duas vezes.',
        ],

        'queue-restart' => [
            'label' => 'Reiniciar workers',
            'description' => 'Sinaliza para os workers encerrarem após o job atual — use depois de alterar código de job.',
        ],
        'queue-failed' => [
            'label' => 'Listar jobs falhos',
            'description' => 'Mostra a fila de jobs que falharam.',
        ],
        'queue-retry-all' => [
            'label' => 'Reprocessar jobs falhos',
            'description' => 'Devolve todos os jobs falhos para a fila.',
        ],
        'queue-flush' => [
            'label' => 'Descartar jobs falhos',
            'description' => 'Apaga a lista de jobs falhos sem reprocessar.',
        ],

        'filament-upgrade' => [
            'label' => 'Atualizar Filament',
            'description' => 'Republica assets e limpa caches do Filament após atualizar o pacote.',
        ],
        'filament-optimize' => [
            'label' => 'Otimizar Filament',
            'description' => 'Cacheia componentes e ícones do Blade.',
        ],
        'filament-optimize-clear' => [
            'label' => 'Limpar otimização do Filament',
            'description' => 'Remove os caches de componentes e ícones.',
        ],
        'filament-assets' => [
            'label' => 'Republicar assets',
            'description' => 'Copia CSS/JS do Filament para a pasta public.',
        ],
        'filament-cache-components' => [
            'label' => 'Cachear componentes',
            'description' => 'Gera o cache de componentes do Filament.',
        ],
        'filament-clear-cached-components' => [
            'label' => 'Limpar componentes cacheados',
            'description' => 'Remove o cache de componentes do Filament.',
        ],

        'storage-link' => [
            'label' => 'Criar link do storage',
            'description' => 'Cria public/storage apontando para storage/app/public.',
        ],
        'composer-install' => [
            'label' => 'Instalar dependências PHP',
            'description' => 'composer install na pasta do projeto. Pode demorar.',
        ],
        'composer-dump' => [
            'label' => 'Regerar autoload',
            'description' => 'Recria o autoload otimizado do Composer.',
        ],
        'npm-install' => [
            'label' => 'Instalar dependências JS',
            'description' => 'npm install na pasta do projeto. Pode demorar.',
        ],
        'npm-build' => [
            'label' => 'Build de produção',
            'description' => 'npm run build — gera os assets do Vite.',
        ],
    ],
];
