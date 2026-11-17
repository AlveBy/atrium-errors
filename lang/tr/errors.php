<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Hatalar',
    'health' => 'Site sağlığı',
    'subtitle' => 'Sitenin ve sayfalarının hataları, her arıza için bir satır',

    'statuses' => [
        'new' => 'Çözülmemiş',
        'resolved' => 'Çözülmüş',
        'ignored' => 'Yok sayılan',
        'all' => 'Tümü',
    ],

    'levels' => [
        'fatal' => 'Ölümcül',
        'error' => 'Hata',
        'warning' => 'Uyarı',
        'info' => 'Bilgi',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Kuyruk işi',
        'schedule' => 'Zamanlanmış görev',
        'browser' => 'Tarayıcı',
        'message' => 'Mesaj',
    ],

    'drivers' => [
        'database' => 'Site veritabanı (yönetim ekranları)',
        'sentry' => 'Sentry veya GlitchTip',
        'log' => 'Uygulama günlüğü',
    ],

    'list' => [
        'search' => 'Sınıfa, mesaja veya yere göre ara',
        'site' => 'Site',
        'all_sites' => 'Tüm siteler',
        'level' => 'Seviye',
        'any_level' => 'Herhangi bir seviye',
        'kind' => 'Kaynak',
        'any_kind' => 'Herhangi bir kaynak',
        'status' => 'Durum',
        'release' => 'Sürüm',
        'any_release' => 'Herhangi bir sürüm',
        'path' => 'Adres',
        'path_placeholder' => '/catalog',
        'sort' => 'Sırala',
        'sorts' => [
            'last_seen_at' => 'Son görülme',
            'first_seen_at' => 'İlk görülme',
            'times_seen' => 'En çok olay',
            'users_seen' => 'En çok kullanıcı',
        ],
        'filter' => 'Göster',
        'reset' => 'Sıfırla',
        'empty' => 'Burada hata yok. Güzel haber.',
        'issue' => 'Hata',
        'events' => 'Olaylar',
        'users' => 'Kullanıcılar',
        'last_seen' => 'Son görülme',
        'first_seen' => 'İlk görülme',
    ],

    'issue' => [
        'back' => 'Tüm hatalar',
        'resolve' => 'Çöz',
        'resolve_next' => 'Sonraki sürümde çöz',
        'resolve_next_hint' => 'Yalnızca :release sürümünden sonraki bir sürümde yeniden olursa geri gelir.',
        'ignore' => 'Yok say',
        'reopen' => 'Yeniden aç',
        'trace' => 'Yığın izi',
        'caused_by' => 'Nedeni',
        'all_frames' => 'Framework çağrılarını göster',
        'app_frames' => 'Framework çağrılarını gizle',
        'request' => 'İstek',
        'context' => 'Bağlam',
        'breadcrumbs' => 'Hatadan önce',
        'environment' => 'Ortam',
        'occurrences' => 'Oluşumlar',
        'frequency' => 'Sıklık',
        'last_24h' => '24 saat',
        'last_30d' => '30 gün',
        'top_paths' => 'Adresler',
        'top_releases' => 'Sürümler',
        'first_release' => 'İlk sürüm',
        'last_release' => 'Son sürüm',
        'resolved_in' => ':release sürümünde çözüldü',
        'regressed' => 'Geri geldi',
        'no_event' => 'Bu hata için artık olay saklanmıyor: saklama süresinden daha eskiler.',
        'open' => 'Aç',
        'shown' => 'Gösterilen',
        'time' => 'Zaman',
        'user' => 'Kullanıcı',
        'method' => 'Yöntem',
        'url' => 'Adres',
        'route' => 'Rota',
        'site' => 'Site',
        'locale' => 'Dil',
        'release' => 'Sürüm',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'Hata yeniden açıldı.',
        'resolved' => 'Hata çözüldü.',
        'ignored' => 'Hata yok sayıldı.',
    ],

    'gadget' => [
        'title' => '24 saatteki hatalar',
        'events' => 'olay',
        'new' => 'yeni',
        'regressed' => 'geri geldi',
        'unresolved' => 'çözülmemiş',
        'none' => 'Son 24 saatte hata yok.',
        'top' => 'En gürültülü',
        'all' => 'Tüm hatalar',
        'chart' => 'Saatlik olaylar',
    ],

    'permissions' => [
        'view' => 'Hataları görüntüleme',
    ],

    'settings' => [
        'description' => 'Hataların nerede saklanıp nereye gönderileceği, kaç tane olacağı ve kimin bilgilendirileceği. Boş alan değeri config/atrium-errors.php veya .env dosyasından alır; gizli bilgileri orada tutun.',
        'drivers' => 'Hataları şuraya gönder',
        'drivers_hint' => 'Aynı anda birkaçı olabilir. Yönetim ekranları ve uyarılar için site veritabanı gerekir.',
        'sentry_dsn' => 'Sentry DSN',
        'sentry_dsn_hint' => 'Sentry, GlitchTip veya Sentry uyumlu başka bir hizmetten: https://anahtar@sunucu/proje. .env içinde ATRIUM_ERRORS_SENTRY_DSN olarak tutmak daha iyi.',
        'sample_rate' => 'Sakla, %',
        'sample_rate_hint' => 'Saklanan olayların oranı, 1 ile 100 arası.',
        'per_minute' => 'Dakikada en fazla',
        'per_minute_hint' => 'Bir dakikada bunun üzerindeki olaylar atılır. 0: sınır yok.',
        'retention_days' => 'Olayları sakla, gün',
        'alert_email' => 'Uyarı e-postaları',
        'alert_email_hint' => 'Adresler virgülle ayrılır.',
        'telegram_token' => 'Telegram bot belirteci',
        'telegram_token_hint' => '@BotFather verir. .env içinde ATRIUM_ERRORS_TELEGRAM_TOKEN olarak tutmak daha iyi.',
        'telegram_chat' => 'Telegram sohbet kimliği',
        'alert_new' => 'Yeni hatada uyar',
        'alert_regression' => 'Çözülmüş bir hata geri geldiğinde uyar',
        'spike_per_hour' => 'Ani artış: bir hatanın saatlik olay sayısı',
        'spike_per_hour_hint' => 'Bir saatte bundan fazlası uyarı gönderir. 0: kapalı.',
        'browser_site' => 'Sitedeki JavaScript hatalarını yakala',
        'browser_admin' => 'Yönetimdeki JavaScript hatalarını yakala',
    ],

    'alerts' => [
        'purpose' => 'Hata uyarıları',
        'subject' => [
            'new' => ':site sitesinde yeni hata: :class',
            'regression' => ':site sitesinde bir hata geri geldi: :class',
            'spike' => ':site sitesinde hata artışı: :class',
        ],
        'where' => 'Yer: :place',
        'counts' => 'Olay: :events, kullanıcı: :users',
        'release' => 'Sürüm: :release',
    ],

    'prune' => [
        'invalid' => 'Gün sayısı 1 veya daha fazla olmalı.',
        'done' => ':days günden eski :events olay ve :issues hata silindi.',
    ],

    'test' => [
        'no_drivers' => 'Hiçbir hata sürücüsü açık değil.',
        'message' => 'Atrium errors: bir test hatası, güvenle çözülebilir',
        'skipped' => 'Gönderilmedi: izleme kapalı ya da örnekleme veya dakika sınırı onu attı.',
        'sent' => ':id test hatası şuraya gönderildi: :drivers.',
    ],
];
