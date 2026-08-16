<?php

return [
    'navigation_label' => 'Agente',
    'title' => 'Agente de supervisão',

    'cards' => [
        'registration' => 'Registro no Windows',
        'registered' => 'Registrado',
        'not_registered' => 'Não registrado',
        'no_method' => 'Nenhum método instalado',
        'state' => 'Estado',
        'heartbeat' => 'Heartbeat',
        'alive' => 'Ativo',
        'dead' => 'Parado',
        'never' => 'Nunca executou',
    ],

    'methods' => [
        'task' => 'Agendador de Tarefas',
        'nssm' => 'Serviço do Windows (NSSM)',
    ],

    'triggers' => [
        'logon' => 'Ao entrar no Windows (usuário atual)',
        'boot' => 'Na inicialização (SYSTEM, antes do login)',
    ],

    'fields' => [
        'method' => 'Método',
        'trigger' => 'Quando iniciar',
    ],

    'hints' => [
        'trigger' => 'No modo "usuário atual" os serviços rodam com a sua conta, como hoje. No modo SYSTEM eles sobem antes de qualquer login.',
    ],

    'actions' => [
        'install' => 'Registrar no Windows',
        'uninstall' => 'Remover registro',
        'start' => 'Iniciar agente',
        'stop' => 'Parar agente',
        'refresh' => 'Atualizar',
    ],

    'confirmations' => [
        'stop' => 'Sem o agente, o reinício automático e o início com o Windows param de funcionar. Continuar?',
        'uninstall' => 'O agente deixará de iniciar sozinho com o Windows. Continuar?',
    ],

    'elevation' => [
        'heading' => 'O painel não está rodando como administrador',
        'body' => 'Registrar tarefa ou serviço no Windows exige elevação. Abra um terminal como Administrador e rode:',
    ],

    'details' => [
        'heading' => 'O que será registrado',
        'php' => 'Binário do PHP',
        'project' => 'Diretório do projeto',
        'command' => 'Comando',
        'task_name' => 'Nome da tarefa',
    ],

    'status' => [
        'task_detail' => 'Última execução: :last_run — resultado: :result',
        'service_detail' => 'Inicialização: :start_type',
    ],

    'messages' => [
        'needs_elevation' => 'É preciso rodar como administrador para registrar o agente no Windows.',
        'installed_task' => 'Agente registrado no Agendador de Tarefas como ":name".',
        'installed_service' => 'Agente registrado como serviço do Windows ":name".',
        'uninstalled' => 'Registro do agente removido.',
        'not_installed' => 'O agente não está registrado no Windows.',
        'nssm_missing' => 'NSSM não encontrado em ":path". Instale o NSSM ou use o Agendador de Tarefas.',
        'started' => 'Agente iniciado.',
        'stopped' => 'Agente parado.',
        'unknown_error' => 'Não foi possível concluir a operação no Windows.',
    ],
];
