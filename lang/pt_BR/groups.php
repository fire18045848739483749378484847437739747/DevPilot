<?php

return [
    'resource' => [
        'model_label' => 'Grupo',
        'model_plural_label' => 'Grupos',
        'navigation_label' => 'Grupos',
    ],

    'fields' => [
        'name' => 'Nome',
        'description' => 'Descrição',
        'color' => 'Cor',
        'start_delay_seconds' => 'Pausa entre serviços',
        'services_count' => 'Serviços',
        'running_count' => 'No ar',
        'members' => 'Ordem de subida',
    ],

    'colors' => [
        'primary' => 'Verde',
        'info' => 'Azul',
        'warning' => 'Âmbar',
        'danger' => 'Vermelho',
        'gray' => 'Cinza',
    ],

    'hints' => [
        'start_delay_seconds' => 'Tempo de espera antes de subir o próximo serviço do grupo.',
    ],

    'actions' => [
        'start' => 'Subir stack',
        'stop' => 'Derrubar stack',
        'restart' => 'Reiniciar stack',
    ],

    'confirmations' => [
        'start' => 'Todos os serviços parados deste grupo serão iniciados na ordem de subida. Continuar?',
        'stop' => 'Todos os serviços em execução deste grupo serão parados na ordem inversa. Continuar?',
        'restart' => 'O grupo será derrubado e subido novamente. Continuar?',
    ],

    'messages' => [
        'done' => ':group — :ok concluído(s), :failed com falha, :skipped ignorado(s).',
    ],

    'empty' => [
        'heading' => 'Nenhum grupo cadastrado',
        'description' => 'Agrupe app, fila e vite para subir e derrubar o projeto inteiro com um clique.',
    ],
];
