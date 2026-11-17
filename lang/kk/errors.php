<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Қателер',
    'health' => 'Сайт жағдайы',
    'subtitle' => 'Сайт пен оның беттеріндегі қателер, бір ақау — бір жол',

    'statuses' => [
        'new' => 'Шешілмеген',
        'resolved' => 'Шешілген',
        'ignored' => 'Жасырылған',
        'all' => 'Барлығы',
    ],

    'levels' => [
        'fatal' => 'Фатальды',
        'error' => 'Қате',
        'warning' => 'Ескерту',
        'info' => 'Мәлімет',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Кезек тапсырмасы',
        'schedule' => 'Кесте бойынша тапсырма',
        'browser' => 'Браузер',
        'message' => 'Хабарлама',
    ],

    'drivers' => [
        'database' => 'Сайт дерекқоры (әкімші экрандары)',
        'sentry' => 'Sentry немесе GlitchTip',
        'log' => 'Қолданба журналы',
    ],

    'list' => [
        'search' => 'Класс, мәтін немесе орын бойынша іздеу',
        'site' => 'Сайт',
        'all_sites' => 'Барлық сайттар',
        'level' => 'Деңгей',
        'any_level' => 'Кез келген деңгей',
        'kind' => 'Көз',
        'any_kind' => 'Кез келген көз',
        'status' => 'Мәртебе',
        'release' => 'Релиз',
        'any_release' => 'Кез келген релиз',
        'path' => 'Мекенжай',
        'path_placeholder' => '/catalog',
        'sort' => 'Реті',
        'sorts' => [
            'last_seen_at' => 'Алдымен соңғылары',
            'first_seen_at' => 'Алдымен жаңадан пайда болғандар',
            'times_seen' => 'Оқиғасы көбірек',
            'users_seen' => 'Адамы көбірек',
        ],
        'filter' => 'Көрсету',
        'reset' => 'Тазалау',
        'empty' => 'Қателер жоқ. Жақсы жаңалық.',
        'issue' => 'Қате',
        'events' => 'Оқиғалар',
        'users' => 'Адамдар',
        'last_seen' => 'Соңғы рет',
        'first_seen' => 'Алғаш рет',
    ],

    'issue' => [
        'back' => 'Барлық қателер',
        'resolve' => 'Шешілді',
        'resolve_next' => 'Келесі релизде шешілді',
        'resolve_next_hint' => 'Қате :release релизінен кейінгі релизде қайталанса ғана қайта оралады.',
        'ignore' => 'Жасыру',
        'reopen' => 'Қайта ашу',
        'trace' => 'Шақырулар стегі',
        'caused_by' => 'Себебі',
        'all_frames' => 'Фреймворк шақыруларын көрсету',
        'app_frames' => 'Фреймворк шақыруларын жасыру',
        'request' => 'Сұраныс',
        'context' => 'Контекст',
        'breadcrumbs' => 'Қатеге дейін',
        'environment' => 'Орта',
        'occurrences' => 'Жағдайлар',
        'frequency' => 'Жиілігі',
        'last_24h' => '24 сағат',
        'last_30d' => '30 күн',
        'top_paths' => 'Мекенжайлар',
        'top_releases' => 'Релиздер',
        'first_release' => 'Алғашқы релиз',
        'last_release' => 'Соңғы релиз',
        'resolved_in' => ':release релизінде шешілді',
        'regressed' => 'Қайта пайда болды',
        'no_event' => 'Бұл қатенің жағдайлары енді сақталмайды: олар сақтау мерзімінен ескі.',
        'open' => 'Ашу',
        'shown' => 'Көрсетілді',
        'time' => 'Уақыт',
        'user' => 'Пайдаланушы',
        'method' => 'Әдіс',
        'url' => 'Мекенжай',
        'route' => 'Маршрут',
        'site' => 'Сайт',
        'locale' => 'Тіл',
        'release' => 'Релиз',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'Қате қайта ашылды.',
        'resolved' => 'Қате шешілді деп белгіленді.',
        'ignored' => 'Қате жасырылды.',
    ],

    'gadget' => [
        'title' => 'Тәуліктегі қателер',
        'events' => 'оқиға',
        'new' => 'жаңа',
        'regressed' => 'қайта оралды',
        'unresolved' => 'шешілмеген',
        'none' => 'Соңғы тәулікте қателер болған жоқ.',
        'top' => 'Ең жиі',
        'all' => 'Барлық қателер',
        'chart' => 'Сағат бойынша оқиғалар',
    ],

    'permissions' => [
        'view' => 'Қателерді қарау',
    ],

    'settings' => [
        'description' => 'Қателерді қайда сақтап, қайда жіберу керек, қанша және кімге хабарлау керек. Бос өріс мәнді config/atrium-errors.php немесе .env файлынан алады — құпияларды сонда сақтаған жөн.',
        'drivers' => 'Қателерді жіберу',
        'drivers_hint' => 'Бірден бірнеше жерге болады. Әкімші экрандары мен хабарландыруларға сайт дерекқоры қажет.',
        'sentry_dsn' => 'Sentry DSN',
        'sentry_dsn_hint' => 'Sentry, GlitchTip немесе Sentry-мен үйлесімді басқа қызметтен: https://кілт@хост/жоба. .env файлында ATRIUM_ERRORS_SENTRY_DSN ретінде сақтаған жөн.',
        'sample_rate' => 'Сақтау, %',
        'sample_rate_hint' => 'Сақталатын оқиғалар үлесі, 1-ден 100-ге дейін.',
        'per_minute' => 'Минутына ең көбі',
        'per_minute_hint' => 'Бір минутта осы саннан асатын оқиғалар тасталады. 0 — шектеусіз.',
        'retention_days' => 'Оқиғаларды сақтау, күн',
        'alert_email' => 'Хабарландыру поштасы',
        'alert_email_hint' => 'Мекенжайлар үтір арқылы.',
        'telegram_token' => 'Telegram бот токені',
        'telegram_token_hint' => '@BotFather береді. .env файлында ATRIUM_ERRORS_TELEGRAM_TOKEN ретінде сақтаған жөн.',
        'telegram_chat' => 'Telegram чат ID',
        'alert_new' => 'Жаңа қате туралы хабарлау',
        'alert_regression' => 'Шешілген қате қайта оралғанда хабарлау',
        'spike_per_hour' => 'Өсім: бір қатенің сағатына оқиғалары',
        'spike_per_hour_hint' => 'Сағатына осыдан көп болса — хабарландыру келеді. 0 — өшірулі.',
        'browser_site' => 'Сайттағы JavaScript қателерін ұстау',
        'browser_admin' => 'Әкімші панеліндегі JavaScript қателерін ұстау',
    ],

    'alerts' => [
        'purpose' => 'Қателер туралы хабарландырулар',
        'subject' => [
            'new' => ':site сайтында жаңа қате: :class',
            'regression' => ':site сайтында қате қайта пайда болды: :class',
            'spike' => ':site сайтында қателер өсімі: :class',
        ],
        'where' => 'Қайда: :place',
        'counts' => 'Оқиға: :events, адам: :users',
        'release' => 'Релиз: :release',
    ],

    'prune' => [
        'invalid' => 'Күн саны кемінде 1 болуы керек.',
        'done' => 'Жойылды: :events оқиға, :issues қате — барлығы :days күннен ескі.',
    ],

    'test' => [
        'no_drivers' => 'Қателерді жіберудің бірде-бір тәсілі қосылмаған.',
        'message' => 'Atrium errors: тексеру қатесі, оны шешілді деп белгілеуге болады',
        'skipped' => 'Жіберілмеді: жинау өшірулі немесе оқиғаны іріктеу не минуттық лимит қиып тастады.',
        'sent' => ':id тексеру қатесі жіберілді: :drivers.',
    ],
];
