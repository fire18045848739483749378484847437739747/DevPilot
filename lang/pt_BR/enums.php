<?php

return [
    'service_status' => [
        'running' => 'Rodando',
        'stopped' => 'Parado',
        'starting' => 'Iniciando',
        'restarting' => 'Reiniciando',
        'error' => 'Erro',
    ],

    'service_environment' => [
        'development' => 'Desenvolvimento',
        'staging' => 'Homologação',
        'production' => 'Produção',
        'testing' => 'Testes',
    ],

    'restart_policy' => [
        'always' => 'Sempre',
        'on_failure' => 'Em caso de falha',
        'manual' => 'Manual',
    ],

    'health_status' => [
        'unknown' => 'Não verificado',
        'ok' => 'Saudável',
        'degraded' => 'Instável',
        'failing' => 'Com falha',
    ],

    'log_level' => [
        'debug' => 'Depuração',
        'info' => 'Informação',
        'warning' => 'Aviso',
        'error' => 'Erro',
        'critical' => 'Crítico',
    ],
];
