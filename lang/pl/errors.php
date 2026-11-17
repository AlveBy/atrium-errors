<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Błędy',
    'health' => 'Stan witryny',
    'subtitle' => 'Błędy witryny i jej stron, jeden wiersz na jedną usterkę',

    'statuses' => [
        'new' => 'Nierozwiązane',
        'resolved' => 'Rozwiązane',
        'ignored' => 'Ignorowane',
        'all' => 'Wszystkie',
    ],

    'levels' => [
        'fatal' => 'Krytyczny',
        'error' => 'Błąd',
        'warning' => 'Ostrzeżenie',
        'info' => 'Informacja',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Zadanie kolejki',
        'schedule' => 'Zadanie cykliczne',
        'browser' => 'Przeglądarka',
        'message' => 'Komunikat',
    ],

    'drivers' => [
        'database' => 'Baza danych witryny (ekrany panelu)',
        'sentry' => 'Sentry lub GlitchTip',
        'log' => 'Dziennik aplikacji',
    ],

    'list' => [
        'search' => 'Szukaj po klasie, treści lub miejscu',
        'site' => 'Witryna',
        'all_sites' => 'Wszystkie witryny',
        'level' => 'Poziom',
        'any_level' => 'Dowolny poziom',
        'kind' => 'Źródło',
        'any_kind' => 'Dowolne źródło',
        'status' => 'Status',
        'release' => 'Wydanie',
        'any_release' => 'Dowolne wydanie',
        'path' => 'Adres',
        'path_placeholder' => '/catalog',
        'sort' => 'Sortuj',
        'sorts' => [
            'last_seen_at' => 'Ostatnio widziane',
            'first_seen_at' => 'Pierwszy raz widziane',
            'times_seen' => 'Najwięcej zdarzeń',
            'users_seen' => 'Najwięcej użytkowników',
        ],
        'filter' => 'Pokaż',
        'reset' => 'Resetuj',
        'empty' => 'Brak błędów. Dobra wiadomość.',
        'issue' => 'Błąd',
        'events' => 'Zdarzenia',
        'users' => 'Użytkownicy',
        'last_seen' => 'Ostatnio',
        'first_seen' => 'Pierwszy raz',
    ],

    'issue' => [
        'back' => 'Wszystkie błędy',
        'resolve' => 'Rozwiąż',
        'resolve_next' => 'Rozwiąż w następnym wydaniu',
        'resolve_next_hint' => 'Wróci tylko wtedy, gdy wystąpi w wydaniu nowszym niż :release.',
        'ignore' => 'Ignoruj',
        'reopen' => 'Otwórz ponownie',
        'trace' => 'Ślad stosu',
        'caused_by' => 'Spowodowany przez',
        'all_frames' => 'Pokaż wywołania frameworka',
        'app_frames' => 'Ukryj wywołania frameworka',
        'request' => 'Żądanie',
        'context' => 'Kontekst',
        'breadcrumbs' => 'Przed błędem',
        'environment' => 'Środowisko',
        'occurrences' => 'Wystąpienia',
        'frequency' => 'Częstotliwość',
        'last_24h' => '24 godziny',
        'last_30d' => '30 dni',
        'top_paths' => 'Adresy',
        'top_releases' => 'Wydania',
        'first_release' => 'Pierwsze wydanie',
        'last_release' => 'Ostatnie wydanie',
        'resolved_in' => 'Rozwiązano w wydaniu :release',
        'regressed' => 'Wrócił',
        'no_event' => 'Zdarzenia tego błędu nie są już przechowywane: są starsze niż okres przechowywania.',
        'open' => 'Otwórz',
        'shown' => 'Wyświetlono',
        'time' => 'Czas',
        'user' => 'Użytkownik',
        'method' => 'Metoda',
        'url' => 'Adres',
        'route' => 'Trasa',
        'site' => 'Witryna',
        'locale' => 'Język',
        'release' => 'Wydanie',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'Błąd został otwarty ponownie.',
        'resolved' => 'Błąd został rozwiązany.',
        'ignored' => 'Błąd jest ignorowany.',
    ],

    'gadget' => [
        'title' => 'Błędy z 24 godzin',
        'events' => 'zdarzeń',
        'new' => 'nowych',
        'regressed' => 'wróciło',
        'unresolved' => 'nierozwiązanych',
        'none' => 'Brak błędów w ostatnich 24 godzinach.',
        'top' => 'Najgłośniejsze',
        'all' => 'Wszystkie błędy',
        'chart' => 'Zdarzenia na godzinę',
    ],

    'permissions' => [
        'view' => 'Przeglądanie błędów',
    ],

    'settings' => [
        'description' => 'Gdzie błędy są zapisywane i wysyłane, ile ich i kogo powiadamiać. Puste pole bierze wartość z config/atrium-errors.php lub .env; sekrety lepiej trzymać tam.',
        'drivers' => 'Wysyłaj błędy do',
        'drivers_hint' => 'Kilka naraz. Ekrany panelu i alerty wymagają bazy danych witryny.',
        'sentry_dsn' => 'DSN Sentry',
        'sentry_dsn_hint' => 'Z Sentry, GlitchTip lub innej usługi zgodnej z Sentry: https://klucz@host/projekt. Lepiej w .env jako ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Zachowuj, %',
        'sample_rate_hint' => 'Odsetek zachowywanych zdarzeń, od 1 do 100.',
        'per_minute' => 'Najwyżej na minutę',
        'per_minute_hint' => 'Zdarzenia ponad ten limit w ciągu minuty są odrzucane. 0: bez limitu.',
        'retention_days' => 'Przechowuj zdarzenia, dni',
        'alert_email' => 'E-maile z alertami',
        'alert_email_hint' => 'Adresy oddzielone przecinkami.',
        'telegram_token' => 'Token bota Telegram',
        'telegram_token_hint' => 'Od @BotFather. Lepiej w .env jako ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'ID czatu Telegram',
        'alert_new' => 'Alert o nowym błędzie',
        'alert_regression' => 'Alert, gdy rozwiązany błąd wróci',
        'spike_per_hour' => 'Skok: zdarzeń jednego błędu na godzinę',
        'spike_per_hour_hint' => 'Więcej niż tyle w ciągu godziny wysyła alert. 0: wyłączone.',
        'browser_site' => 'Wychwytuj błędy JavaScript na witrynie',
        'browser_admin' => 'Wychwytuj błędy JavaScript w panelu',
    ],

    'alerts' => [
        'purpose' => 'Alerty o błędach',
        'subject' => [
            'new' => 'Nowy błąd na :site: :class',
            'regression' => 'Błąd wrócił na :site: :class',
            'spike' => 'Skok liczby błędów na :site: :class',
        ],
        'where' => 'Gdzie: :place',
        'counts' => 'Zdarzenia: :events, użytkownicy: :users',
        'release' => 'Wydanie: :release',
    ],

    'prune' => [
        'invalid' => 'Liczba dni musi wynosić co najmniej 1.',
        'done' => 'Usunięto zdarzeń: :events i błędów: :issues starszych niż :days dni.',
    ],

    'test' => [
        'no_drivers' => 'Żaden sterownik błędów nie jest włączony.',
        'message' => 'Atrium errors: testowy błąd, można go spokojnie rozwiązać',
        'skipped' => 'Nie wysłano: śledzenie jest wyłączone albo próbkowanie lub limit na minutę go odrzuciły.',
        'sent' => 'Testowy błąd :id wysłano do: :drivers.',
    ],
];
