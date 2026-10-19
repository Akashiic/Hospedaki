<?php

/*
| Modo demonstração: para mostrar o Hospedaki a donos de pousada.
|
| Ligado, a pousada indicada ganha um login aberto no painel ("Entrar como dono") e volta
| todo dia ao estado inicial, com pedidos e bloqueios de exemplo. Isso apaga TODOS os pedidos
| dessa pousada: nunca ligue numa instalação que recebe reservas de verdade.
*/
return [
    'enabled' => (bool) env('DEMO_MODE', false),

    'property_slug' => env('DEMO_PROPERTY_SLUG', 'espaco-bela-vista'),

    // Usuário do login aberto; a senha é aleatória e ninguém precisa dela.
    'user_email' => env('DEMO_USER_EMAIL', 'demo@exemplo.com.br'),
];
