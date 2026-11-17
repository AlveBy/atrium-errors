<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'エラー',
    'health' => 'サイトの状態',
    'subtitle' => 'サイトとそのページのエラー。不具合ごとに1行',

    'statuses' => [
        'new' => '未解決',
        'resolved' => '解決済み',
        'ignored' => '無視',
        'all' => 'すべて',
    ],

    'levels' => [
        'fatal' => '致命的',
        'error' => 'エラー',
        'warning' => '警告',
        'info' => '情報',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'キュージョブ',
        'schedule' => 'スケジュールタスク',
        'browser' => 'ブラウザ',
        'message' => 'メッセージ',
    ],

    'drivers' => [
        'database' => 'サイトのデータベース（管理画面用）',
        'sentry' => 'Sentry または GlitchTip',
        'log' => 'アプリケーションログ',
    ],

    'list' => [
        'search' => 'クラス、メッセージ、場所で検索',
        'site' => 'サイト',
        'all_sites' => 'すべてのサイト',
        'level' => 'レベル',
        'any_level' => 'すべてのレベル',
        'kind' => 'ソース',
        'any_kind' => 'すべてのソース',
        'status' => 'ステータス',
        'release' => 'リリース',
        'any_release' => 'すべてのリリース',
        'path' => 'アドレス',
        'path_placeholder' => '/catalog',
        'sort' => '並び順',
        'sorts' => [
            'last_seen_at' => '最終発生',
            'first_seen_at' => '初回発生',
            'times_seen' => 'イベント数が多い順',
            'users_seen' => 'ユーザー数が多い順',
        ],
        'filter' => '表示',
        'reset' => 'リセット',
        'empty' => 'エラーはありません。良いニュースです。',
        'issue' => 'エラー',
        'events' => 'イベント',
        'users' => 'ユーザー',
        'last_seen' => '最終発生',
        'first_seen' => '初回発生',
    ],

    'issue' => [
        'back' => 'すべてのエラー',
        'resolve' => '解決',
        'resolve_next' => '次のリリースで解決',
        'resolve_next_hint' => ':release より後のリリースで発生した場合にのみ再発扱いになります。',
        'ignore' => '無視',
        'reopen' => '再オープン',
        'trace' => 'スタックトレース',
        'caused_by' => '原因',
        'all_frames' => 'フレームワークのフレームを表示',
        'app_frames' => 'フレームワークのフレームを非表示',
        'request' => 'リクエスト',
        'context' => 'コンテキスト',
        'breadcrumbs' => 'エラーの前',
        'environment' => '環境',
        'occurrences' => '発生履歴',
        'frequency' => '頻度',
        'last_24h' => '24時間',
        'last_30d' => '30日',
        'top_paths' => 'アドレス',
        'top_releases' => 'リリース',
        'first_release' => '最初のリリース',
        'last_release' => '最新のリリース',
        'resolved_in' => 'リリース :release で解決',
        'regressed' => '再発',
        'no_event' => 'このエラーのイベントはもう保存されていません。保持期間より古いためです。',
        'open' => '開く',
        'shown' => '表示済み',
        'time' => '時刻',
        'user' => 'ユーザー',
        'method' => 'メソッド',
        'url' => 'アドレス',
        'route' => 'ルート',
        'site' => 'サイト',
        'locale' => '言語',
        'release' => 'リリース',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'エラーを再オープンしました。',
        'resolved' => 'エラーを解決済みにしました。',
        'ignored' => 'エラーを無視しました。',
    ],

    'gadget' => [
        'title' => '24時間のエラー',
        'events' => 'イベント',
        'new' => '新規',
        'regressed' => '再発',
        'unresolved' => '未解決',
        'none' => '過去24時間のエラーはありません。',
        'top' => '多い順',
        'all' => 'すべてのエラー',
        'chart' => '1時間あたりのイベント',
    ],

    'permissions' => [
        'view' => 'エラーの閲覧',
    ],

    'settings' => [
        'description' => 'エラーの保存先と送信先、件数、通知する相手を設定します。空欄の場合は config/atrium-errors.php または .env の値が使われます。秘密情報はそちらで管理してください。',
        'drivers' => 'エラーの送信先',
        'drivers_hint' => '複数を同時に選べます。管理画面とアラートにはサイトのデータベースが必要です。',
        'sentry_dsn' => 'Sentry DSN',
        'sentry_dsn_hint' => 'Sentry、GlitchTip、または Sentry 互換サービスで取得できます: https://キー@ホスト/プロジェクト。.env に ATRIUM_ERRORS_SENTRY_DSN として設定するのがおすすめです。',
        'sample_rate' => '保存率、%',
        'sample_rate_hint' => '保存するイベントの割合（1〜100）。',
        'per_minute' => '1分あたりの上限',
        'per_minute_hint' => '1分間にこの数を超えたイベントは破棄されます。0: 無制限。',
        'retention_days' => 'イベントの保存日数',
        'alert_email' => 'アラートのメール',
        'alert_email_hint' => 'アドレスはカンマで区切ります。',
        'telegram_token' => 'Telegram ボットのトークン',
        'telegram_token_hint' => '@BotFather で取得できます。.env に ATRIUM_ERRORS_TELEGRAM_TOKEN として設定するのがおすすめです。',
        'telegram_chat' => 'Telegram チャット ID',
        'alert_new' => '新しいエラーで通知',
        'alert_regression' => '解決済みのエラーが再発したときに通知',
        'spike_per_hour' => 'スパイク: 1つのエラーの1時間あたりのイベント数',
        'spike_per_hour_hint' => '1時間にこの数を超えるとアラートを送ります。0: オフ。',
        'browser_site' => 'サイトの JavaScript エラーを収集',
        'browser_admin' => '管理画面の JavaScript エラーを収集',
    ],

    'alerts' => [
        'purpose' => 'エラーアラート',
        'subject' => [
            'new' => ':site で新しいエラー: :class',
            'regression' => ':site でエラーが再発: :class',
            'spike' => ':site でエラーが急増: :class',
        ],
        'where' => '場所: :place',
        'counts' => 'イベント: :events、ユーザー: :users',
        'release' => 'リリース: :release',
    ],

    'prune' => [
        'invalid' => '日数は1以上にしてください。',
        'done' => ':days 日より古いイベント :events 件とエラー :issues 件を削除しました。',
    ],

    'test' => [
        'no_drivers' => '有効なエラードライバーがありません。',
        'message' => 'Atrium errors: テストエラーです。解決済みにして問題ありません',
        'skipped' => '送信されませんでした: 追跡がオフか、サンプリングまたは1分あたりの上限で破棄されました。',
        'sent' => 'テストエラー :id を送信しました: :drivers。',
    ],
];
