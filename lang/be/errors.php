<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Памылкі',
    'health' => 'Стан сайта',
    'subtitle' => 'Памылкі сайта і яго старонак, адзін радок на адну паломку',

    'statuses' => [
        'new' => 'Не вырашаны',
        'resolved' => 'Вырашаны',
        'ignored' => 'Схаваны',
        'all' => 'Усе',
    ],

    'levels' => [
        'fatal' => 'Фатальная',
        'error' => 'Памылка',
        'warning' => 'Папярэджанне',
        'info' => 'Звесткі',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Задача чаргі',
        'schedule' => 'Задача па раскладзе',
        'browser' => 'Браўзер',
        'message' => 'Паведамленне',
    ],

    'drivers' => [
        'database' => 'База сайта (экраны адмінкі)',
        'sentry' => 'Sentry або GlitchTip',
        'log' => 'Журнал праграмы',
    ],

    'list' => [
        'search' => 'Пошук па класе, тэксце або месцы',
        'site' => 'Сайт',
        'all_sites' => 'Усе сайты',
        'level' => 'Узровень',
        'any_level' => 'Любы ўзровень',
        'kind' => 'Крыніца',
        'any_kind' => 'Любая крыніца',
        'status' => 'Статус',
        'release' => 'Рэліз',
        'any_release' => 'Любы рэліз',
        'path' => 'Адрас',
        'path_placeholder' => '/catalog',
        'sort' => 'Парадак',
        'sorts' => [
            'last_seen_at' => 'Спачатку свежыя',
            'first_seen_at' => 'Спачатку новыя',
            'times_seen' => 'Больш за ўсё падзей',
            'users_seen' => 'Больш за ўсё людзей',
        ],
        'filter' => 'Паказаць',
        'reset' => 'Скінуць',
        'empty' => 'Памылак няма. Добрая навіна.',
        'issue' => 'Памылка',
        'events' => 'Падзеі',
        'users' => 'Людзі',
        'last_seen' => 'Апошні раз',
        'first_seen' => 'Упершыню',
    ],

    'issue' => [
        'back' => 'Усе памылкі',
        'resolve' => 'Вырашана',
        'resolve_next' => 'Вырашана ў наступным рэлізе',
        'resolve_next_hint' => 'Памылка вернецца, толькі калі паўторыцца ў рэлізе навейшым за :release.',
        'ignore' => 'Схаваць',
        'reopen' => 'Адкрыць зноў',
        'trace' => 'Стэк выклікаў',
        'caused_by' => 'Прычына',
        'all_frames' => 'Паказаць выклікі фрэймворка',
        'app_frames' => 'Схаваць выклікі фрэймворка',
        'request' => 'Запыт',
        'context' => 'Кантэкст',
        'breadcrumbs' => 'Перад памылкай',
        'environment' => 'Асяроддзе',
        'occurrences' => 'Выпадкі',
        'frequency' => 'Частата',
        'last_24h' => '24 гадзіны',
        'last_30d' => '30 дзён',
        'top_paths' => 'Адрасы',
        'top_releases' => 'Рэлізы',
        'first_release' => 'Першы рэліз',
        'last_release' => 'Апошні рэліз',
        'resolved_in' => 'Вырашана ў рэлізе :release',
        'regressed' => 'Вярнулася',
        'no_event' => 'Выпадкаў гэтай памылкі больш не захоўваецца: яны старэйшыя за тэрмін захоўвання.',
        'open' => 'Адкрыць',
        'shown' => 'Паказана',
        'time' => 'Час',
        'user' => 'Карыстальнік',
        'method' => 'Метад',
        'url' => 'Адрас',
        'route' => 'Маршрут',
        'site' => 'Сайт',
        'locale' => 'Мова',
        'release' => 'Рэліз',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'Памылка зноў адкрыта.',
        'resolved' => 'Памылка пазначана вырашанай.',
        'ignored' => 'Памылка схавана.',
    ],

    'gadget' => [
        'title' => 'Памылкі за суткі',
        'events' => 'падзей',
        'new' => 'новых',
        'regressed' => 'вярнуліся',
        'unresolved' => 'не вырашана',
        'none' => 'За апошнія суткі памылак не было.',
        'top' => 'Часцей за ўсё',
        'all' => 'Усе памылкі',
        'chart' => 'Падзеі па гадзінах',
    ],

    'permissions' => [
        'view' => 'Прагляд памылак',
    ],

    'settings' => [
        'description' => 'Куды складаць і адпраўляць памылкі, колькі і каго паведамляць. Пустое поле бярэ значэнне з config/atrium-errors.php або .env — сакрэты лепш трымаць там.',
        'drivers' => 'Адпраўляць памылкі',
        'drivers_hint' => 'Можна ў некалькі месцаў адразу. Экранам адмінкі і апавяшчэнням патрэбная база сайта.',
        'sentry_dsn' => 'DSN Sentry',
        'sentry_dsn_hint' => 'З Sentry, GlitchTip або іншага сервісу, сумяшчальнага з Sentry: https://ключ@хост/праект. Лепш у .env як ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Захоўваць, %',
        'sample_rate_hint' => 'Доля падзей, якія захоўваюцца, ад 1 да 100.',
        'per_minute' => 'Не больш за хвіліну',
        'per_minute_hint' => 'Падзеі звыш гэтага ліку за хвіліну адкідаюцца. 0 — без абмежавання.',
        'retention_days' => 'Захоўваць падзеі, дзён',
        'alert_email' => 'Пошта для апавяшчэнняў',
        'alert_email_hint' => 'Адрасы праз коску.',
        'telegram_token' => 'Токен бота Telegram',
        'telegram_token_hint' => 'Выдае @BotFather. Лепш у .env як ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'ID чата Telegram',
        'alert_new' => 'Паведамляць пра новую памылку',
        'alert_regression' => 'Паведамляць, калі вырашаная памылка вярнулася',
        'spike_per_hour' => 'Усплёск: падзей адной памылкі за гадзіну',
        'spike_per_hour_hint' => 'Калі за гадзіну больш — прыйдзе апавяшчэнне. 0 — выключана.',
        'browser_site' => 'Лавіць памылкі JavaScript на сайце',
        'browser_admin' => 'Лавіць памылкі JavaScript у адмінцы',
    ],

    'alerts' => [
        'purpose' => 'Апавяшчэнні пра памылкі',
        'subject' => [
            'new' => 'Новая памылка на :site: :class',
            'regression' => 'Памылка вярнулася на :site: :class',
            'spike' => 'Усплёск памылкі на :site: :class',
        ],
        'where' => 'Дзе: :place',
        'counts' => 'Падзей: :events, людзей: :users',
        'release' => 'Рэліз: :release',
    ],

    'prune' => [
        'invalid' => 'Колькасць дзён павінна быць не менш за 1.',
        'done' => 'Выдалена падзей: :events, памылак: :issues — усё старэйшае за :days дн.',
    ],

    'test' => [
        'no_drivers' => 'Не ўключана ніводнага спосабу адпраўкі памылак.',
        'message' => 'Atrium errors: праверачная памылка, яе можна пазначыць вырашанай',
        'skipped' => 'Не адпраўлена: збор выключаны або падзею адсеклі выбарка ці ліміт за хвіліну.',
        'sent' => 'Праверачная памылка :id адпраўлена: :drivers.',
    ],
];
