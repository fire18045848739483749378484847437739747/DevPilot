<?php

return [
    'control_panel' => 'Controle rápido',
    'control_panel_desc' => 'Inicie, pare ou reinicie cada serviço sem sair do painel.',

    'no_services' => 'Nenhum serviço cadastrado',
    'no_services_desc' => 'Cadastre um serviço para começar a monitorá-lo.',
    'create_service' => 'Cadastrar serviço',

    'actions' => [
        'start_all' => 'Iniciar todos',
        'stop_all' => 'Parar todos',
        'restart_all' => 'Reiniciar todos',
    ],

    'confirmations' => [
        'start_all' => 'Todos os serviços parados serão iniciados. Serviços de produção não são afetados. Continuar?',
        'stop_all' => 'Todos os serviços em execução serão parados. Serviços de produção não são afetados. Continuar?',
        'restart_all' => 'Todos os serviços em execução serão reiniciados. Serviços de produção não são afetados. Continuar?',
    ],

    'messages' => [
        'bulk_done' => ':ok com sucesso, :failed com falha, :skipped ignorados.',
    ],
];
