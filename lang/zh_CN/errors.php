<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => '错误',
    'health' => '站点健康',
    'subtitle' => '站点及其页面的错误，每个问题一行',

    'statuses' => [
        'new' => '未解决',
        'resolved' => '已解决',
        'ignored' => '已忽略',
        'all' => '全部',
    ],

    'levels' => [
        'fatal' => '致命',
        'error' => '错误',
        'warning' => '警告',
        'info' => '信息',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => '队列任务',
        'schedule' => '计划任务',
        'browser' => '浏览器',
        'message' => '消息',
    ],

    'drivers' => [
        'database' => '站点数据库（后台页面）',
        'sentry' => 'Sentry 或 GlitchTip',
        'log' => '应用日志',
    ],

    'list' => [
        'search' => '按类、消息或位置搜索',
        'site' => '站点',
        'all_sites' => '所有站点',
        'level' => '级别',
        'any_level' => '任意级别',
        'kind' => '来源',
        'any_kind' => '任意来源',
        'status' => '状态',
        'release' => '版本',
        'any_release' => '任意版本',
        'path' => '地址',
        'path_placeholder' => '/catalog',
        'sort' => '排序',
        'sorts' => [
            'last_seen_at' => '最近出现',
            'first_seen_at' => '首次出现',
            'times_seen' => '事件最多',
            'users_seen' => '用户最多',
        ],
        'filter' => '显示',
        'reset' => '重置',
        'empty' => '这里没有错误，好消息。',
        'issue' => '错误',
        'events' => '事件',
        'users' => '用户',
        'last_seen' => '最近出现',
        'first_seen' => '首次出现',
    ],

    'issue' => [
        'back' => '所有错误',
        'resolve' => '标记为已解决',
        'resolve_next' => '在下一版本中解决',
        'resolve_next_hint' => '仅当在 :release 之后的版本中再次出现时才会重新打开。',
        'ignore' => '忽略',
        'reopen' => '重新打开',
        'trace' => '堆栈跟踪',
        'caused_by' => '原因',
        'all_frames' => '显示框架调用帧',
        'app_frames' => '隐藏框架调用帧',
        'request' => '请求',
        'context' => '上下文',
        'breadcrumbs' => '错误发生前',
        'environment' => '环境',
        'occurrences' => '出现记录',
        'frequency' => '频率',
        'last_24h' => '24 小时',
        'last_30d' => '30 天',
        'top_paths' => '地址',
        'top_releases' => '版本',
        'first_release' => '首个版本',
        'last_release' => '最新版本',
        'resolved_in' => '已在版本 :release 中解决',
        'regressed' => '再次出现',
        'no_event' => '此错误已不再保留任何事件：它们已超过保留期限。',
        'open' => '打开',
        'shown' => '已显示',
        'time' => '时间',
        'user' => '用户',
        'method' => '方法',
        'url' => '地址',
        'route' => '路由',
        'site' => '站点',
        'locale' => '语言',
        'release' => '版本',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => '错误已重新打开。',
        'resolved' => '错误已解决。',
        'ignored' => '错误已忽略。',
    ],

    'gadget' => [
        'title' => '24 小时内的错误',
        'events' => '个事件',
        'new' => '新增',
        'regressed' => '再次出现',
        'unresolved' => '未解决',
        'none' => '过去 24 小时内没有错误。',
        'top' => '最频繁',
        'all' => '所有错误',
        'chart' => '每小时事件数',
    ],

    'permissions' => [
        'view' => '查看错误',
    ],

    'settings' => [
        'description' => '错误存放和发送的位置、数量以及通知对象。留空的字段会取自 config/atrium-errors.php 或 .env；密钥请保存在那里。',
        'drivers' => '将错误发送到',
        'drivers_hint' => '可同时选择多个。后台页面和告警需要站点数据库。',
        'sentry_dsn' => 'Sentry DSN',
        'sentry_dsn_hint' => '来自 Sentry、GlitchTip 或其他兼容 Sentry 的服务：https://密钥@主机/项目。建议放在 .env 中，变量名 ATRIUM_ERRORS_SENTRY_DSN。',
        'sample_rate' => '保留比例，%',
        'sample_rate_hint' => '保留的事件占比，1 到 100。',
        'per_minute' => '每分钟最多',
        'per_minute_hint' => '一分钟内超过此数量的事件会被丢弃。0：不限制。',
        'retention_days' => '事件保留天数',
        'alert_email' => '告警邮箱',
        'alert_email_hint' => '多个地址用逗号分隔。',
        'telegram_token' => 'Telegram 机器人令牌',
        'telegram_token_hint' => '由 @BotFather 提供。建议放在 .env 中，变量名 ATRIUM_ERRORS_TELEGRAM_TOKEN。',
        'telegram_chat' => 'Telegram 聊天 ID',
        'alert_new' => '出现新错误时告警',
        'alert_regression' => '已解决的错误再次出现时告警',
        'spike_per_hour' => '激增：单个错误每小时的事件数',
        'spike_per_hour_hint' => '一小时内超过此数量会发送告警。0：关闭。',
        'browser_site' => '捕获站点上的 JavaScript 错误',
        'browser_admin' => '捕获后台中的 JavaScript 错误',
    ],

    'alerts' => [
        'purpose' => '错误告警',
        'subject' => [
            'new' => ':site 上出现新错误：:class',
            'regression' => ':site 上的错误再次出现：:class',
            'spike' => ':site 上的错误激增：:class',
        ],
        'where' => '位置：:place',
        'counts' => '事件：:events，用户：:users',
        'release' => '版本：:release',
    ],

    'prune' => [
        'invalid' => '天数必须为 1 或更大。',
        'done' => '已删除 :days 天前的 :events 个事件和 :issues 个错误。',
    ],

    'test' => [
        'no_drivers' => '未启用任何错误驱动。',
        'message' => 'Atrium errors: 测试错误，可放心标记为已解决',
        'skipped' => '未发送：跟踪已关闭，或被采样或每分钟上限丢弃。',
        'sent' => '测试错误 :id 已发送到：:drivers。',
    ],
];
