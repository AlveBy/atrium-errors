<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Xatolar',
    'health' => 'Sayt holati',
    'subtitle' => 'Sayt va uning sahifalaridagi xatolar, bitta nosozlik — bitta qator',

    'statuses' => [
        'new' => 'Hal qilinmagan',
        'resolved' => 'Hal qilingan',
        'ignored' => 'Yashirilgan',
        'all' => 'Barchasi',
    ],

    'levels' => [
        'fatal' => 'Halokatli',
        'error' => 'Xato',
        'warning' => 'Ogohlantirish',
        'info' => 'Ma\'lumot',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Navbat vazifasi',
        'schedule' => 'Jadval bo\'yicha vazifa',
        'browser' => 'Brauzer',
        'message' => 'Xabar',
    ],

    'drivers' => [
        'database' => 'Sayt bazasi (admin ekranlari)',
        'sentry' => 'Sentry yoki GlitchTip',
        'log' => 'Ilova jurnali',
    ],

    'list' => [
        'search' => 'Klass, matn yoki joy bo\'yicha qidirish',
        'site' => 'Sayt',
        'all_sites' => 'Barcha saytlar',
        'level' => 'Daraja',
        'any_level' => 'Istalgan daraja',
        'kind' => 'Manba',
        'any_kind' => 'Istalgan manba',
        'status' => 'Holat',
        'release' => 'Reliz',
        'any_release' => 'Istalgan reliz',
        'path' => 'Manzil',
        'path_placeholder' => '/catalog',
        'sort' => 'Tartib',
        'sorts' => [
            'last_seen_at' => 'Avval yangilari',
            'first_seen_at' => 'Avval yangi paydo bo\'lganlari',
            'times_seen' => 'Hodisalari ko\'proq',
            'users_seen' => 'Odamlari ko\'proq',
        ],
        'filter' => 'Ko\'rsatish',
        'reset' => 'Tozalash',
        'empty' => 'Xatolar yo\'q. Yaxshi xabar.',
        'issue' => 'Xato',
        'events' => 'Hodisalar',
        'users' => 'Odamlar',
        'last_seen' => 'Oxirgi marta',
        'first_seen' => 'Birinchi marta',
    ],

    'issue' => [
        'back' => 'Barcha xatolar',
        'resolve' => 'Hal qilindi',
        'resolve_next' => 'Keyingi relizda hal qilindi',
        'resolve_next_hint' => 'Xato faqat :release dan keyingi relizda takrorlansagina qaytadi.',
        'ignore' => 'Yashirish',
        'reopen' => 'Qayta ochish',
        'trace' => 'Chaqiruvlar steki',
        'caused_by' => 'Sababi',
        'all_frames' => 'Freymvork chaqiruvlarini ko\'rsatish',
        'app_frames' => 'Freymvork chaqiruvlarini yashirish',
        'request' => 'So\'rov',
        'context' => 'Kontekst',
        'breadcrumbs' => 'Xatodan oldin',
        'environment' => 'Muhit',
        'occurrences' => 'Holatlar',
        'frequency' => 'Chastota',
        'last_24h' => '24 soat',
        'last_30d' => '30 kun',
        'top_paths' => 'Manzillar',
        'top_releases' => 'Relizlar',
        'first_release' => 'Birinchi reliz',
        'last_release' => 'Oxirgi reliz',
        'resolved_in' => ':release relizida hal qilindi',
        'regressed' => 'Qaytdi',
        'no_event' => 'Bu xatoning holatlari endi saqlanmaydi: ular saqlash muddatidan eski.',
        'open' => 'Ochish',
        'shown' => 'Ko\'rsatilgan',
        'time' => 'Vaqt',
        'user' => 'Foydalanuvchi',
        'method' => 'Usul',
        'url' => 'Manzil',
        'route' => 'Marshrut',
        'site' => 'Sayt',
        'locale' => 'Til',
        'release' => 'Reliz',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'Xato qayta ochildi.',
        'resolved' => 'Xato hal qilingan deb belgilandi.',
        'ignored' => 'Xato yashirildi.',
    ],

    'gadget' => [
        'title' => 'Sutkadagi xatolar',
        'events' => 'hodisa',
        'new' => 'yangi',
        'regressed' => 'qaytdi',
        'unresolved' => 'hal qilinmagan',
        'none' => 'Oxirgi sutkada xatolar bo\'lmadi.',
        'top' => 'Eng ko\'p',
        'all' => 'Barcha xatolar',
        'chart' => 'Soatlar bo\'yicha hodisalar',
    ],

    'permissions' => [
        'view' => 'Xatolarni koʻrish',
    ],

    'settings' => [
        'description' => 'Xatolar qayerga saqlanadi va yuboriladi, qancha va kimga xabar beriladi. Bo\'sh maydon qiymatni config/atrium-errors.php yoki .env dan oladi — sirlarni o\'sha yerda saqlagan ma\'qul.',
        'drivers' => 'Xatolarni yuborish',
        'drivers_hint' => 'Bir vaqtning o\'zida bir nechta joyga bo\'ladi. Admin ekranlari va ogohlantirishlar uchun sayt bazasi kerak.',
        'sentry_dsn' => 'Sentry DSN',
        'sentry_dsn_hint' => 'Sentry, GlitchTip yoki Sentry bilan mos boshqa xizmatdan: https://kalit@host/loyiha. .env da ATRIUM_ERRORS_SENTRY_DSN sifatida saqlagan ma\'qul.',
        'sample_rate' => 'Saqlash, %',
        'sample_rate_hint' => 'Saqlanadigan hodisalar ulushi, 1 dan 100 gacha.',
        'per_minute' => 'Daqiqasiga ko\'pi bilan',
        'per_minute_hint' => 'Bir daqiqada shundan oshgan hodisalar tashlab yuboriladi. 0 — cheklovsiz.',
        'retention_days' => 'Hodisalarni saqlash, kun',
        'alert_email' => 'Ogohlantirish pochtasi',
        'alert_email_hint' => 'Manzillar vergul bilan.',
        'telegram_token' => 'Telegram bot tokeni',
        'telegram_token_hint' => '@BotFather beradi. .env da ATRIUM_ERRORS_TELEGRAM_TOKEN sifatida saqlagan ma\'qul.',
        'telegram_chat' => 'Telegram chat ID',
        'alert_new' => 'Yangi xato haqida xabar berish',
        'alert_regression' => 'Hal qilingan xato qaytganda xabar berish',
        'spike_per_hour' => 'Keskin o\'sish: bitta xatoning soatiga hodisalari',
        'spike_per_hour_hint' => 'Soatiga bundan ko\'p bo\'lsa — ogohlantirish keladi. 0 — o\'chirilgan.',
        'browser_site' => 'Saytdagi JavaScript xatolarini ushlash',
        'browser_admin' => 'Adminkadagi JavaScript xatolarini ushlash',
    ],

    'alerts' => [
        'purpose' => 'Xatolar haqida ogohlantirishlar',
        'subject' => [
            'new' => ':site saytida yangi xato: :class',
            'regression' => ':site saytida xato qaytdi: :class',
            'spike' => ':site saytida xatolar keskin o\'sdi: :class',
        ],
        'where' => 'Qayerda: :place',
        'counts' => 'Hodisalar: :events, odamlar: :users',
        'release' => 'Reliz: :release',
    ],

    'prune' => [
        'invalid' => 'Kunlar soni kamida 1 bo\'lishi kerak.',
        'done' => 'O\'chirildi: :events hodisa, :issues xato — hammasi :days kundan eski.',
    ],

    'test' => [
        'no_drivers' => 'Xatolarni yuborishning birorta usuli yoqilmagan.',
        'message' => 'Atrium errors: tekshiruv xatosi, uni hal qilingan deb belgilash mumkin',
        'skipped' => 'Yuborilmadi: yig\'ish o\'chirilgan yoki hodisani tanlama yoki daqiqalik limit kesib tashladi.',
        'sent' => 'Tekshiruv xatosi :id yuborildi: :drivers.',
    ],
];
