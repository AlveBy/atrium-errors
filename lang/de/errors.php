<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Fehler',
    'health' => 'Website-Zustand',
    'subtitle' => 'Fehler der Website und ihrer Seiten, eine Zeile pro Problem',

    'statuses' => [
        'new' => 'Ungelöst',
        'resolved' => 'Gelöst',
        'ignored' => 'Ignoriert',
        'all' => 'Alle',
    ],

    'levels' => [
        'fatal' => 'Fatal',
        'error' => 'Fehler',
        'warning' => 'Warnung',
        'info' => 'Info',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Queue-Job',
        'schedule' => 'Geplante Aufgabe',
        'browser' => 'Browser',
        'message' => 'Meldung',
    ],

    'drivers' => [
        'database' => 'Website-Datenbank (die Admin-Ansichten)',
        'sentry' => 'Sentry oder GlitchTip',
        'log' => 'Anwendungslog',
    ],

    'list' => [
        'search' => 'Suche nach Klasse, Meldung oder Ort',
        'site' => 'Website',
        'all_sites' => 'Alle Websites',
        'level' => 'Level',
        'any_level' => 'Beliebiges Level',
        'kind' => 'Quelle',
        'any_kind' => 'Beliebige Quelle',
        'status' => 'Status',
        'release' => 'Release',
        'any_release' => 'Beliebiges Release',
        'path' => 'Adresse',
        'path_placeholder' => '/catalog',
        'sort' => 'Sortierung',
        'sorts' => [
            'last_seen_at' => 'Zuletzt gesehen',
            'first_seen_at' => 'Zuerst gesehen',
            'times_seen' => 'Meiste Ereignisse',
            'users_seen' => 'Meiste Nutzer',
        ],
        'filter' => 'Anzeigen',
        'reset' => 'Zurücksetzen',
        'empty' => 'Keine Fehler hier. Gute Nachrichten.',
        'issue' => 'Fehler',
        'events' => 'Ereignisse',
        'users' => 'Nutzer',
        'last_seen' => 'Zuletzt gesehen',
        'first_seen' => 'Zuerst gesehen',
    ],

    'issue' => [
        'back' => 'Alle Fehler',
        'resolve' => 'Lösen',
        'resolve_next' => 'Im nächsten Release lösen',
        'resolve_next_hint' => 'Er kommt nur zurück, wenn er in einem Release nach :release auftritt.',
        'ignore' => 'Ignorieren',
        'reopen' => 'Wieder öffnen',
        'trace' => 'Stacktrace',
        'caused_by' => 'Verursacht durch',
        'all_frames' => 'Framework-Frames anzeigen',
        'app_frames' => 'Framework-Frames ausblenden',
        'request' => 'Anfrage',
        'context' => 'Kontext',
        'breadcrumbs' => 'Vor dem Fehler',
        'environment' => 'Umgebung',
        'occurrences' => 'Vorkommen',
        'frequency' => 'Häufigkeit',
        'last_24h' => '24 Stunden',
        'last_30d' => '30 Tage',
        'top_paths' => 'Adressen',
        'top_releases' => 'Releases',
        'first_release' => 'Erstes Release',
        'last_release' => 'Letztes Release',
        'resolved_in' => 'Gelöst in Release :release',
        'regressed' => 'Wieder aufgetreten',
        'no_event' => 'Für diesen Fehler werden keine Ereignisse mehr gespeichert: Sie sind älter als die Aufbewahrungsdauer.',
        'open' => 'Öffnen',
        'shown' => 'Angezeigt',
        'time' => 'Zeit',
        'user' => 'Nutzer',
        'method' => 'Methode',
        'url' => 'Adresse',
        'route' => 'Route',
        'site' => 'Website',
        'locale' => 'Sprache',
        'release' => 'Release',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'Der Fehler ist wieder offen.',
        'resolved' => 'Der Fehler ist gelöst.',
        'ignored' => 'Der Fehler wird ignoriert.',
    ],

    'gadget' => [
        'title' => 'Fehler in 24 Stunden',
        'events' => 'Ereignisse',
        'new' => 'neu',
        'regressed' => 'wieder aufgetreten',
        'unresolved' => 'ungelöst',
        'none' => 'Keine Fehler in den letzten 24 Stunden.',
        'top' => 'Am lautesten',
        'all' => 'Alle Fehler',
        'chart' => 'Ereignisse pro Stunde',
    ],

    'permissions' => [
        'view' => 'Fehler ansehen',
    ],

    'settings' => [
        'description' => 'Wo Fehler gespeichert und hingeschickt werden, wie viele und wer benachrichtigt wird. Ein leeres Feld übernimmt den Wert aus config/atrium-errors.php oder .env; Geheimnisse gehören dorthin.',
        'drivers' => 'Fehler senden an',
        'drivers_hint' => 'Mehrere gleichzeitig. Die Admin-Ansichten und Benachrichtigungen brauchen die Website-Datenbank.',
        'sentry_dsn' => 'Sentry-DSN',
        'sentry_dsn_hint' => 'Von Sentry, GlitchTip oder einem anderen Sentry-kompatiblen Dienst: https://schlüssel@host/projekt. Besser in .env als ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Behalten, %',
        'sample_rate_hint' => 'Anteil der behaltenen Ereignisse, 1 bis 100.',
        'per_minute' => 'Höchstens pro Minute',
        'per_minute_hint' => 'Ereignisse darüber hinaus werden innerhalb einer Minute verworfen. 0: keine Grenze.',
        'retention_days' => 'Ereignisse behalten, Tage',
        'alert_email' => 'Benachrichtigungs-E-Mails',
        'alert_email_hint' => 'Adressen, durch Kommas getrennt.',
        'telegram_token' => 'Telegram-Bot-Token',
        'telegram_token_hint' => 'Von @BotFather. Besser in .env als ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'Telegram-Chat-ID',
        'alert_new' => 'Bei neuem Fehler benachrichtigen',
        'alert_regression' => 'Benachrichtigen, wenn ein gelöster Fehler wiederkommt',
        'spike_per_hour' => 'Spitze: Ereignisse eines Fehlers pro Stunde',
        'spike_per_hour_hint' => 'Mehr als das in einer Stunde löst eine Benachrichtigung aus. 0: aus.',
        'browser_site' => 'JavaScript-Fehler auf der Website erfassen',
        'browser_admin' => 'JavaScript-Fehler im Adminbereich erfassen',
    ],

    'alerts' => [
        'purpose' => 'Fehlerbenachrichtigungen',
        'subject' => [
            'new' => 'Neuer Fehler auf :site: :class',
            'regression' => 'Fehler wieder aufgetreten auf :site: :class',
            'spike' => 'Fehlerspitze auf :site: :class',
        ],
        'where' => 'Ort: :place',
        'counts' => 'Ereignisse: :events, Nutzer: :users',
        'release' => 'Release: :release',
    ],

    'prune' => [
        'invalid' => 'Die Anzahl der Tage muss mindestens 1 betragen.',
        'done' => ':events Ereignisse und :issues Fehler gelöscht, die älter als :days Tage sind.',
    ],

    'test' => [
        'no_drivers' => 'Kein Fehlertreiber ist eingeschaltet.',
        'message' => 'Atrium errors: ein Testfehler, kann bedenkenlos gelöst werden',
        'skipped' => 'Nicht gesendet: Die Erfassung ist aus oder Sampling bzw. das Limit pro Minute hat ihn verworfen.',
        'sent' => 'Testfehler :id gesendet an: :drivers.',
    ],
];
