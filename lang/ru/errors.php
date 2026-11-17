<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Ошибки',
    'health' => 'Состояние сайта',
    'subtitle' => 'Ошибки сайта и его страниц, одна строка на одну поломку',

    'statuses' => [
        'new' => 'Не решены',
        'resolved' => 'Решены',
        'ignored' => 'Скрыты',
        'all' => 'Все',
    ],

    'levels' => [
        'fatal' => 'Фатальная',
        'error' => 'Ошибка',
        'warning' => 'Предупреждение',
        'info' => 'Сведения',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Задача очереди',
        'schedule' => 'Задача по расписанию',
        'browser' => 'Браузер',
        'message' => 'Сообщение',
    ],

    'drivers' => [
        'database' => 'База сайта (экраны админки)',
        'sentry' => 'Sentry или GlitchTip',
        'log' => 'Журнал приложения',
    ],

    'list' => [
        'search' => 'Поиск по классу, тексту или месту',
        'site' => 'Сайт',
        'all_sites' => 'Все сайты',
        'level' => 'Уровень',
        'any_level' => 'Любой уровень',
        'kind' => 'Источник',
        'any_kind' => 'Любой источник',
        'status' => 'Статус',
        'release' => 'Релиз',
        'any_release' => 'Любой релиз',
        'path' => 'Адрес',
        'path_placeholder' => '/catalog',
        'sort' => 'Порядок',
        'sorts' => [
            'last_seen_at' => 'Сначала свежие',
            'first_seen_at' => 'Сначала новые',
            'times_seen' => 'Больше всего событий',
            'users_seen' => 'Больше всего людей',
        ],
        'filter' => 'Показать',
        'reset' => 'Сбросить',
        'empty' => 'Ошибок нет. Хорошая новость.',
        'issue' => 'Ошибка',
        'events' => 'События',
        'users' => 'Люди',
        'last_seen' => 'Последний раз',
        'first_seen' => 'Впервые',
    ],

    'issue' => [
        'back' => 'Все ошибки',
        'resolve' => 'Решено',
        'resolve_next' => 'Решено в следующем релизе',
        'resolve_next_hint' => 'Ошибка вернётся, только если повторится в релизе новее :release.',
        'ignore' => 'Скрыть',
        'reopen' => 'Открыть снова',
        'trace' => 'Стек вызовов',
        'caused_by' => 'Причина',
        'all_frames' => 'Показать вызовы фреймворка',
        'app_frames' => 'Скрыть вызовы фреймворка',
        'request' => 'Запрос',
        'context' => 'Контекст',
        'breadcrumbs' => 'Перед ошибкой',
        'environment' => 'Окружение',
        'occurrences' => 'Случаи',
        'frequency' => 'Частота',
        'last_24h' => '24 часа',
        'last_30d' => '30 дней',
        'top_paths' => 'Адреса',
        'top_releases' => 'Релизы',
        'first_release' => 'Первый релиз',
        'last_release' => 'Последний релиз',
        'resolved_in' => 'Решено в релизе :release',
        'regressed' => 'Вернулась',
        'no_event' => 'Случаев этой ошибки больше не хранится: они старше срока хранения.',
        'open' => 'Открыть',
        'shown' => 'Показан',
        'time' => 'Время',
        'user' => 'Пользователь',
        'method' => 'Метод',
        'url' => 'Адрес',
        'route' => 'Маршрут',
        'site' => 'Сайт',
        'locale' => 'Язык',
        'release' => 'Релиз',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'Ошибка снова открыта.',
        'resolved' => 'Ошибка отмечена решённой.',
        'ignored' => 'Ошибка скрыта.',
    ],

    'gadget' => [
        'title' => 'Ошибки за сутки',
        'events' => 'событий',
        'new' => 'новых',
        'regressed' => 'вернулись',
        'unresolved' => 'не решено',
        'none' => 'За последние сутки ошибок не было.',
        'top' => 'Чаще всего',
        'all' => 'Все ошибки',
        'chart' => 'События по часам',
    ],

    'permissions' => [
        'view' => 'Просмотр ошибок',
    ],

    'settings' => [
        'description' => 'Куда складывать и отправлять ошибки, сколько и кому сообщать. Пустое поле берёт значение из config/atrium-errors.php или .env — секреты лучше держать там.',
        'drivers' => 'Отправлять ошибки',
        'drivers_hint' => 'Можно в несколько мест сразу. Экранам админки и оповещениям нужна база сайта.',
        'sentry_dsn' => 'DSN Sentry',
        'sentry_dsn_hint' => 'Из Sentry, GlitchTip или другого сервиса, совместимого с Sentry: https://ключ@хост/проект. Лучше в .env как ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Сохранять, %',
        'sample_rate_hint' => 'Доля сохраняемых событий, от 1 до 100.',
        'per_minute' => 'Не больше в минуту',
        'per_minute_hint' => 'События сверх этого числа за минуту отбрасываются. 0 — без ограничения.',
        'retention_days' => 'Хранить события, дней',
        'alert_email' => 'Почта для оповещений',
        'alert_email_hint' => 'Адреса через запятую.',
        'telegram_token' => 'Токен бота Telegram',
        'telegram_token_hint' => 'Выдаёт @BotFather. Лучше в .env как ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'ID чата Telegram',
        'alert_new' => 'Сообщать о новой ошибке',
        'alert_regression' => 'Сообщать, когда решённая ошибка вернулась',
        'spike_per_hour' => 'Всплеск: событий одной ошибки в час',
        'spike_per_hour_hint' => 'Если за час больше — придёт оповещение. 0 — выключено.',
        'browser_site' => 'Ловить ошибки JavaScript на сайте',
        'browser_admin' => 'Ловить ошибки JavaScript в админке',
    ],

    'alerts' => [
        'purpose' => 'Оповещения об ошибках',
        'subject' => [
            'new' => 'Новая ошибка на :site: :class',
            'regression' => 'Ошибка вернулась на :site: :class',
            'spike' => 'Всплеск ошибки на :site: :class',
        ],
        'where' => 'Где: :place',
        'counts' => 'Событий: :events, людей: :users',
        'release' => 'Релиз: :release',
    ],

    'prune' => [
        'invalid' => 'Число дней должно быть не меньше 1.',
        'done' => 'Удалено событий: :events, ошибок: :issues — всё старше :days дн.',
    ],

    'test' => [
        'no_drivers' => 'Не включено ни одного способа отправки ошибок.',
        'message' => 'Atrium errors: проверочная ошибка, её можно отметить решённой',
        'skipped' => 'Не отправлено: сбор выключен или событие отсекли выборка либо лимит в минуту.',
        'sent' => 'Проверочная ошибка :id отправлена: :drivers.',
    ],
];
