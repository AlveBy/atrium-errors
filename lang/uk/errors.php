<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Помилки',
    'health' => 'Стан сайту',
    'subtitle' => 'Помилки сайту та його сторінок, один рядок на одну поломку',

    'statuses' => [
        'new' => 'Не вирішені',
        'resolved' => 'Вирішені',
        'ignored' => 'Приховані',
        'all' => 'Усі',
    ],

    'levels' => [
        'fatal' => 'Фатальна',
        'error' => 'Помилка',
        'warning' => 'Попередження',
        'info' => 'Відомості',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Завдання черги',
        'schedule' => 'Завдання за розкладом',
        'browser' => 'Браузер',
        'message' => 'Повідомлення',
    ],

    'drivers' => [
        'database' => 'База сайту (екрани адмінки)',
        'sentry' => 'Sentry або GlitchTip',
        'log' => 'Журнал застосунку',
    ],

    'list' => [
        'search' => 'Пошук за класом, текстом або місцем',
        'site' => 'Сайт',
        'all_sites' => 'Усі сайти',
        'level' => 'Рівень',
        'any_level' => 'Будь-який рівень',
        'kind' => 'Джерело',
        'any_kind' => 'Будь-яке джерело',
        'status' => 'Статус',
        'release' => 'Реліз',
        'any_release' => 'Будь-який реліз',
        'path' => 'Адреса',
        'path_placeholder' => '/catalog',
        'sort' => 'Порядок',
        'sorts' => [
            'last_seen_at' => 'Спочатку свіжі',
            'first_seen_at' => 'Спочатку нові',
            'times_seen' => 'Найбільше подій',
            'users_seen' => 'Найбільше людей',
        ],
        'filter' => 'Показати',
        'reset' => 'Скинути',
        'empty' => 'Помилок немає. Гарна новина.',
        'issue' => 'Помилка',
        'events' => 'Події',
        'users' => 'Люди',
        'last_seen' => 'Востаннє',
        'first_seen' => 'Уперше',
    ],

    'issue' => [
        'back' => 'Усі помилки',
        'resolve' => 'Вирішено',
        'resolve_next' => 'Вирішено в наступному релізі',
        'resolve_next_hint' => 'Помилка повернеться, лише якщо повториться в релізі новішому за :release.',
        'ignore' => 'Приховати',
        'reopen' => 'Відкрити знову',
        'trace' => 'Стек викликів',
        'caused_by' => 'Причина',
        'all_frames' => 'Показати виклики фреймворку',
        'app_frames' => 'Сховати виклики фреймворку',
        'request' => 'Запит',
        'context' => 'Контекст',
        'breadcrumbs' => 'Перед помилкою',
        'environment' => 'Середовище',
        'occurrences' => 'Випадки',
        'frequency' => 'Частота',
        'last_24h' => '24 години',
        'last_30d' => '30 днів',
        'top_paths' => 'Адреси',
        'top_releases' => 'Релізи',
        'first_release' => 'Перший реліз',
        'last_release' => 'Останній реліз',
        'resolved_in' => 'Вирішено в релізі :release',
        'regressed' => 'Повернулась',
        'no_event' => 'Випадків цієї помилки більше не зберігається: вони старші за строк зберігання.',
        'open' => 'Відкрити',
        'shown' => 'Показано',
        'time' => 'Час',
        'user' => 'Користувач',
        'method' => 'Метод',
        'url' => 'Адреса',
        'route' => 'Маршрут',
        'site' => 'Сайт',
        'locale' => 'Мова',
        'release' => 'Реліз',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'Помилку знову відкрито.',
        'resolved' => 'Помилку позначено вирішеною.',
        'ignored' => 'Помилку приховано.',
    ],

    'gadget' => [
        'title' => 'Помилки за добу',
        'events' => 'подій',
        'new' => 'нових',
        'regressed' => 'повернулись',
        'unresolved' => 'не вирішено',
        'none' => 'За останню добу помилок не було.',
        'top' => 'Найчастіше',
        'all' => 'Усі помилки',
        'chart' => 'Події за годинами',
    ],

    'permissions' => [
        'view' => 'Перегляд помилок',
    ],

    'settings' => [
        'description' => 'Куди складати й надсилати помилки, скільки та кого сповіщати. Порожнє поле бере значення з config/atrium-errors.php або .env — секрети краще тримати там.',
        'drivers' => 'Надсилати помилки',
        'drivers_hint' => 'Можна в кілька місць одразу. Екранам адмінки та сповіщенням потрібна база сайту.',
        'sentry_dsn' => 'DSN Sentry',
        'sentry_dsn_hint' => 'Із Sentry, GlitchTip або іншого сервісу, сумісного із Sentry: https://ключ@хост/проєкт. Краще в .env як ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Зберігати, %',
        'sample_rate_hint' => 'Частка збережених подій, від 1 до 100.',
        'per_minute' => 'Не більше за хвилину',
        'per_minute_hint' => 'Події понад це число за хвилину відкидаються. 0 — без обмежень.',
        'retention_days' => 'Зберігати події, днів',
        'alert_email' => 'Пошта для сповіщень',
        'alert_email_hint' => 'Адреси через кому.',
        'telegram_token' => 'Токен бота Telegram',
        'telegram_token_hint' => 'Видає @BotFather. Краще в .env як ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'ID чату Telegram',
        'alert_new' => 'Повідомляти про нову помилку',
        'alert_regression' => 'Повідомляти, коли вирішена помилка повернулась',
        'spike_per_hour' => 'Сплеск: подій однієї помилки за годину',
        'spike_per_hour_hint' => 'Якщо за годину більше — надійде сповіщення. 0 — вимкнено.',
        'browser_site' => 'Ловити помилки JavaScript на сайті',
        'browser_admin' => 'Ловити помилки JavaScript в адмінці',
    ],

    'alerts' => [
        'purpose' => 'Сповіщення про помилки',
        'subject' => [
            'new' => 'Нова помилка на :site: :class',
            'regression' => 'Помилка повернулась на :site: :class',
            'spike' => 'Сплеск помилки на :site: :class',
        ],
        'where' => 'Де: :place',
        'counts' => 'Подій: :events, людей: :users',
        'release' => 'Реліз: :release',
    ],

    'prune' => [
        'invalid' => 'Кількість днів має бути не менше 1.',
        'done' => 'Видалено подій: :events, помилок: :issues — усе старше за :days дн.',
    ],

    'test' => [
        'no_drivers' => 'Не ввімкнено жодного способу надсилання помилок.',
        'message' => 'Atrium errors: перевірочна помилка, її можна позначити вирішеною',
        'skipped' => 'Не надіслано: збір вимкнено або подію відсікли вибірка чи ліміт за хвилину.',
        'sent' => 'Перевірочну помилку :id надіслано: :drivers.',
    ],
];
