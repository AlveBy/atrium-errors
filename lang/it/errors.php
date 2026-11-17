<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Errori',
    'health' => 'Stato del sito',
    'subtitle' => 'Gli errori del sito e delle sue pagine, una riga per problema',

    'statuses' => [
        'new' => 'Non risolti',
        'resolved' => 'Risolti',
        'ignored' => 'Ignorati',
        'all' => 'Tutti',
    ],

    'levels' => [
        'fatal' => 'Fatale',
        'error' => 'Errore',
        'warning' => 'Avviso',
        'info' => 'Info',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Job in coda',
        'schedule' => 'Attività pianificata',
        'browser' => 'Browser',
        'message' => 'Messaggio',
    ],

    'drivers' => [
        'database' => 'Database del sito (le schermate dell\'amministrazione)',
        'sentry' => 'Sentry o GlitchTip',
        'log' => 'Log dell\'applicazione',
    ],

    'list' => [
        'search' => 'Cerca per classe, messaggio o posizione',
        'site' => 'Sito',
        'all_sites' => 'Tutti i siti',
        'level' => 'Livello',
        'any_level' => 'Qualsiasi livello',
        'kind' => 'Origine',
        'any_kind' => 'Qualsiasi origine',
        'status' => 'Stato',
        'release' => 'Release',
        'any_release' => 'Qualsiasi release',
        'path' => 'Indirizzo',
        'path_placeholder' => '/catalog',
        'sort' => 'Ordina',
        'sorts' => [
            'last_seen_at' => 'Ultima occorrenza',
            'first_seen_at' => 'Prima occorrenza',
            'times_seen' => 'Più eventi',
            'users_seen' => 'Più utenti',
        ],
        'filter' => 'Mostra',
        'reset' => 'Reimposta',
        'empty' => 'Nessun errore qui. Buone notizie.',
        'issue' => 'Errore',
        'events' => 'Eventi',
        'users' => 'Utenti',
        'last_seen' => 'Ultima occorrenza',
        'first_seen' => 'Prima occorrenza',
    ],

    'issue' => [
        'back' => 'Tutti gli errori',
        'resolve' => 'Risolvi',
        'resolve_next' => 'Risolvi nella prossima release',
        'resolve_next_hint' => 'Torna solo se si ripresenta in una release successiva a :release.',
        'ignore' => 'Ignora',
        'reopen' => 'Riapri',
        'trace' => 'Stack trace',
        'caused_by' => 'Causato da',
        'all_frames' => 'Mostra i frame del framework',
        'app_frames' => 'Nascondi i frame del framework',
        'request' => 'Richiesta',
        'context' => 'Contesto',
        'breadcrumbs' => 'Prima dell\'errore',
        'environment' => 'Ambiente',
        'occurrences' => 'Occorrenze',
        'frequency' => 'Frequenza',
        'last_24h' => '24 ore',
        'last_30d' => '30 giorni',
        'top_paths' => 'Indirizzi',
        'top_releases' => 'Release',
        'first_release' => 'Prima release',
        'last_release' => 'Ultima release',
        'resolved_in' => 'Risolto nella release :release',
        'regressed' => 'Ripresentato',
        'no_event' => 'Per questo errore non è più conservato alcun evento: sono più vecchi del periodo di conservazione.',
        'open' => 'Apri',
        'shown' => 'Mostrato',
        'time' => 'Ora',
        'user' => 'Utente',
        'method' => 'Metodo',
        'url' => 'Indirizzo',
        'route' => 'Route',
        'site' => 'Sito',
        'locale' => 'Lingua',
        'release' => 'Release',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'L\'errore è stato riaperto.',
        'resolved' => 'L\'errore è stato risolto.',
        'ignored' => 'L\'errore viene ignorato.',
    ],

    'gadget' => [
        'title' => 'Errori nelle 24 ore',
        'events' => 'eventi',
        'new' => 'nuovi',
        'regressed' => 'ripresentati',
        'unresolved' => 'non risolti',
        'none' => 'Nessun errore nelle ultime 24 ore.',
        'top' => 'I più rumorosi',
        'all' => 'Tutti gli errori',
        'chart' => 'Eventi per ora',
    ],

    'permissions' => [
        'view' => 'Visualizzare gli errori',
    ],

    'settings' => [
        'description' => 'Dove vengono salvati e inviati gli errori, quanti e chi viene avvisato. Un campo vuoto prende il valore da config/atrium-errors.php o .env; tieni lì i segreti.',
        'drivers' => 'Invia gli errori a',
        'drivers_hint' => 'Più destinazioni insieme. Le schermate dell\'amministrazione e gli avvisi richiedono il database del sito.',
        'sentry_dsn' => 'DSN di Sentry',
        'sentry_dsn_hint' => 'Da Sentry, GlitchTip o un altro servizio compatibile con Sentry: https://chiave@host/progetto. Meglio in .env come ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Conserva, %',
        'sample_rate_hint' => 'Percentuale di eventi conservati, da 1 a 100.',
        'per_minute' => 'Massimo al minuto',
        'per_minute_hint' => 'Gli eventi oltre questo numero in un minuto vengono scartati. 0: nessun limite.',
        'retention_days' => 'Conserva gli eventi, giorni',
        'alert_email' => 'E-mail di avviso',
        'alert_email_hint' => 'Indirizzi separati da virgole.',
        'telegram_token' => 'Token del bot Telegram',
        'telegram_token_hint' => 'Lo fornisce @BotFather. Meglio in .env come ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'ID della chat Telegram',
        'alert_new' => 'Avvisa per un nuovo errore',
        'alert_regression' => 'Avvisa quando un errore risolto si ripresenta',
        'spike_per_hour' => 'Picco: eventi di un errore all\'ora',
        'spike_per_hour_hint' => 'Più di questo valore in un\'ora invia un avviso. 0: disattivato.',
        'browser_site' => 'Intercetta gli errori JavaScript sul sito',
        'browser_admin' => 'Intercetta gli errori JavaScript nell\'amministrazione',
    ],

    'alerts' => [
        'purpose' => 'Avvisi di errore',
        'subject' => [
            'new' => 'Nuovo errore su :site: :class',
            'regression' => 'Un errore si è ripresentato su :site: :class',
            'spike' => 'Picco di errori su :site: :class',
        ],
        'where' => 'Dove: :place',
        'counts' => 'Eventi: :events, utenti: :users',
        'release' => 'Release: :release',
    ],

    'prune' => [
        'invalid' => 'Il numero di giorni deve essere 1 o più.',
        'done' => 'Eliminati :events eventi e :issues errori più vecchi di :days giorni.',
    ],

    'test' => [
        'no_drivers' => 'Nessun driver degli errori è attivo.',
        'message' => 'Atrium errors: un errore di prova, si può risolvere senza problemi',
        'skipped' => 'Non inviato: il tracciamento è disattivato, oppure il campionamento o il limite al minuto lo ha scartato.',
        'sent' => 'Errore di prova :id inviato a: :drivers.',
    ],
];
