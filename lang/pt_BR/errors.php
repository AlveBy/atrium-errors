<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Erros',
    'health' => 'Saúde do site',
    'subtitle' => 'Erros do site e de suas páginas, uma linha por problema',

    'statuses' => [
        'new' => 'Não resolvidos',
        'resolved' => 'Resolvidos',
        'ignored' => 'Ignorados',
        'all' => 'Todos',
    ],

    'levels' => [
        'fatal' => 'Fatal',
        'error' => 'Erro',
        'warning' => 'Aviso',
        'info' => 'Info',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Job da fila',
        'schedule' => 'Tarefa agendada',
        'browser' => 'Navegador',
        'message' => 'Mensagem',
    ],

    'drivers' => [
        'database' => 'Banco de dados do site (as telas do admin)',
        'sentry' => 'Sentry ou GlitchTip',
        'log' => 'Log da aplicação',
    ],

    'list' => [
        'search' => 'Buscar por classe, mensagem ou local',
        'site' => 'Site',
        'all_sites' => 'Todos os sites',
        'level' => 'Nível',
        'any_level' => 'Qualquer nível',
        'kind' => 'Origem',
        'any_kind' => 'Qualquer origem',
        'status' => 'Status',
        'release' => 'Versão',
        'any_release' => 'Qualquer versão',
        'path' => 'Endereço',
        'path_placeholder' => '/catalog',
        'sort' => 'Ordem',
        'sorts' => [
            'last_seen_at' => 'Visto por último',
            'first_seen_at' => 'Visto pela primeira vez',
            'times_seen' => 'Mais eventos',
            'users_seen' => 'Mais usuários',
        ],
        'filter' => 'Mostrar',
        'reset' => 'Limpar',
        'empty' => 'Nenhum erro aqui. Boas notícias.',
        'issue' => 'Erro',
        'events' => 'Eventos',
        'users' => 'Usuários',
        'last_seen' => 'Visto por último',
        'first_seen' => 'Visto pela primeira vez',
    ],

    'issue' => [
        'back' => 'Todos os erros',
        'resolve' => 'Resolver',
        'resolve_next' => 'Resolver na próxima versão',
        'resolve_next_hint' => 'Ele só volta se ocorrer em uma versão posterior à :release.',
        'ignore' => 'Ignorar',
        'reopen' => 'Reabrir',
        'trace' => 'Stack trace',
        'caused_by' => 'Causado por',
        'all_frames' => 'Mostrar frames do framework',
        'app_frames' => 'Ocultar frames do framework',
        'request' => 'Requisição',
        'context' => 'Contexto',
        'breadcrumbs' => 'Antes do erro',
        'environment' => 'Ambiente',
        'occurrences' => 'Ocorrências',
        'frequency' => 'Frequência',
        'last_24h' => '24 horas',
        'last_30d' => '30 dias',
        'top_paths' => 'Endereços',
        'top_releases' => 'Versões',
        'first_release' => 'Primeira versão',
        'last_release' => 'Última versão',
        'resolved_in' => 'Resolvido na versão :release',
        'regressed' => 'Voltou',
        'no_event' => 'Não há mais eventos guardados para este erro: eles são mais antigos que o período de retenção.',
        'open' => 'Abrir',
        'shown' => 'Exibido',
        'time' => 'Hora',
        'user' => 'Usuário',
        'method' => 'Método',
        'url' => 'Endereço',
        'route' => 'Rota',
        'site' => 'Site',
        'locale' => 'Idioma',
        'release' => 'Versão',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'O erro foi reaberto.',
        'resolved' => 'O erro foi resolvido.',
        'ignored' => 'O erro está sendo ignorado.',
    ],

    'gadget' => [
        'title' => 'Erros em 24 horas',
        'events' => 'eventos',
        'new' => 'novos',
        'regressed' => 'voltaram',
        'unresolved' => 'não resolvidos',
        'none' => 'Nenhum erro nas últimas 24 horas.',
        'top' => 'Os mais barulhentos',
        'all' => 'Todos os erros',
        'chart' => 'Eventos por hora',
    ],

    'permissions' => [
        'view' => 'Ver erros',
    ],

    'settings' => [
        'description' => 'Onde os erros são guardados e enviados, quantos e quem é avisado. Um campo vazio usa o valor de config/atrium-errors.php ou .env; deixe os segredos lá.',
        'drivers' => 'Enviar erros para',
        'drivers_hint' => 'Vários ao mesmo tempo. As telas do admin e os alertas precisam do banco de dados do site.',
        'sentry_dsn' => 'DSN do Sentry',
        'sentry_dsn_hint' => 'Do Sentry, GlitchTip ou outro serviço compatível com o Sentry: https://chave@host/projeto. Melhor no .env como ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Manter, %',
        'sample_rate_hint' => 'Parcela dos eventos mantidos, de 1 a 100.',
        'per_minute' => 'No máximo por minuto',
        'per_minute_hint' => 'Eventos acima disso em um minuto são descartados. 0: sem limite.',
        'retention_days' => 'Manter eventos, dias',
        'alert_email' => 'E-mails de alerta',
        'alert_email_hint' => 'Endereços separados por vírgulas.',
        'telegram_token' => 'Token do bot do Telegram',
        'telegram_token_hint' => 'Fornecido pelo @BotFather. Melhor no .env como ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'ID do chat do Telegram',
        'alert_new' => 'Alertar sobre um erro novo',
        'alert_regression' => 'Alertar quando um erro resolvido voltar',
        'spike_per_hour' => 'Pico: eventos de um erro por hora',
        'spike_per_hour_hint' => 'Mais do que isso em uma hora envia um alerta. 0: desligado.',
        'browser_site' => 'Capturar erros de JavaScript no site',
        'browser_admin' => 'Capturar erros de JavaScript no admin',
    ],

    'alerts' => [
        'purpose' => 'Alertas de erro',
        'subject' => [
            'new' => 'Novo erro em :site: :class',
            'regression' => 'Um erro voltou em :site: :class',
            'spike' => 'Pico de erros em :site: :class',
        ],
        'where' => 'Onde: :place',
        'counts' => 'Eventos: :events, usuários: :users',
        'release' => 'Versão: :release',
    ],

    'prune' => [
        'invalid' => 'O número de dias deve ser 1 ou mais.',
        'done' => ':events eventos e :issues erros com mais de :days dias foram apagados.',
    ],

    'test' => [
        'no_drivers' => 'Nenhum driver de erros está ligado.',
        'message' => 'Atrium errors: um erro de teste, pode resolver sem medo',
        'skipped' => 'Não enviado: o rastreamento está desligado, ou a amostragem ou o limite por minuto o descartou.',
        'sent' => 'Erro de teste :id enviado para: :drivers.',
    ],
];
