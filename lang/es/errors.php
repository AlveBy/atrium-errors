<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Errores',
    'health' => 'Estado del sitio',
    'subtitle' => 'Errores del sitio y sus páginas, una línea por fallo',

    'statuses' => [
        'new' => 'Sin resolver',
        'resolved' => 'Resueltos',
        'ignored' => 'Ignorados',
        'all' => 'Todos',
    ],

    'levels' => [
        'fatal' => 'Fatal',
        'error' => 'Error',
        'warning' => 'Advertencia',
        'info' => 'Info',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Tarea en cola',
        'schedule' => 'Tarea programada',
        'browser' => 'Navegador',
        'message' => 'Mensaje',
    ],

    'drivers' => [
        'database' => 'Base de datos del sitio (las pantallas del administrador)',
        'sentry' => 'Sentry o GlitchTip',
        'log' => 'Registro de la aplicación',
    ],

    'list' => [
        'search' => 'Buscar por clase, mensaje o lugar',
        'site' => 'Sitio',
        'all_sites' => 'Todos los sitios',
        'level' => 'Nivel',
        'any_level' => 'Cualquier nivel',
        'kind' => 'Origen',
        'any_kind' => 'Cualquier origen',
        'status' => 'Estado',
        'release' => 'Versión',
        'any_release' => 'Cualquier versión',
        'path' => 'Dirección',
        'path_placeholder' => '/catalog',
        'sort' => 'Orden',
        'sorts' => [
            'last_seen_at' => 'Visto por última vez',
            'first_seen_at' => 'Visto por primera vez',
            'times_seen' => 'Más eventos',
            'users_seen' => 'Más usuarios',
        ],
        'filter' => 'Mostrar',
        'reset' => 'Restablecer',
        'empty' => 'No hay errores. Buenas noticias.',
        'issue' => 'Error',
        'events' => 'Eventos',
        'users' => 'Usuarios',
        'last_seen' => 'Visto por última vez',
        'first_seen' => 'Visto por primera vez',
    ],

    'issue' => [
        'back' => 'Todos los errores',
        'resolve' => 'Resolver',
        'resolve_next' => 'Resolver en la próxima versión',
        'resolve_next_hint' => 'Solo vuelve a aparecer si ocurre en una versión posterior a :release.',
        'ignore' => 'Ignorar',
        'reopen' => 'Reabrir',
        'trace' => 'Traza de pila',
        'caused_by' => 'Causado por',
        'all_frames' => 'Mostrar llamadas del framework',
        'app_frames' => 'Ocultar llamadas del framework',
        'request' => 'Solicitud',
        'context' => 'Contexto',
        'breadcrumbs' => 'Antes del error',
        'environment' => 'Entorno',
        'occurrences' => 'Ocurrencias',
        'frequency' => 'Frecuencia',
        'last_24h' => '24 horas',
        'last_30d' => '30 días',
        'top_paths' => 'Direcciones',
        'top_releases' => 'Versiones',
        'first_release' => 'Primera versión',
        'last_release' => 'Última versión',
        'resolved_in' => 'Resuelto en la versión :release',
        'regressed' => 'Reapareció',
        'no_event' => 'Ya no se conservan eventos de este error: son más antiguos que el período de retención.',
        'open' => 'Abrir',
        'shown' => 'Mostrado',
        'time' => 'Hora',
        'user' => 'Usuario',
        'method' => 'Método',
        'url' => 'Dirección',
        'route' => 'Ruta',
        'site' => 'Sitio',
        'locale' => 'Idioma',
        'release' => 'Versión',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'El error se ha vuelto a abrir.',
        'resolved' => 'El error está resuelto.',
        'ignored' => 'El error se ignora.',
    ],

    'gadget' => [
        'title' => 'Errores en 24 horas',
        'events' => 'eventos',
        'new' => 'nuevos',
        'regressed' => 'reaparecieron',
        'unresolved' => 'sin resolver',
        'none' => 'No hay errores en las últimas 24 horas.',
        'top' => 'Los más ruidosos',
        'all' => 'Todos los errores',
        'chart' => 'Eventos por hora',
    ],

    'permissions' => [
        'view' => 'Ver errores',
    ],

    'settings' => [
        'description' => 'Dónde se guardan y se envían los errores, cuántos y a quién se avisa. Un campo vacío toma el valor de config/atrium-errors.php o .env; guarda allí los secretos.',
        'drivers' => 'Enviar errores a',
        'drivers_hint' => 'Varios a la vez. Las pantallas del administrador y las alertas necesitan la base de datos del sitio.',
        'sentry_dsn' => 'DSN de Sentry',
        'sentry_dsn_hint' => 'De Sentry, GlitchTip u otro servicio compatible con Sentry: https://clave@host/proyecto. Mejor en .env como ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Conservar, %',
        'sample_rate_hint' => 'Porcentaje de eventos conservados, de 1 a 100.',
        'per_minute' => 'Máximo por minuto',
        'per_minute_hint' => 'Los eventos por encima de esta cifra en un minuto se descartan. 0: sin límite.',
        'retention_days' => 'Conservar eventos, días',
        'alert_email' => 'Correos de alerta',
        'alert_email_hint' => 'Direcciones separadas por comas.',
        'telegram_token' => 'Token del bot de Telegram',
        'telegram_token_hint' => 'Lo da @BotFather. Mejor en .env como ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'ID del chat de Telegram',
        'alert_new' => 'Avisar de un error nuevo',
        'alert_regression' => 'Avisar cuando un error resuelto vuelve a aparecer',
        'spike_per_hour' => 'Pico: eventos de un error por hora',
        'spike_per_hour_hint' => 'Si se supera en una hora, se envía una alerta. 0: desactivado.',
        'browser_site' => 'Capturar errores de JavaScript en el sitio',
        'browser_admin' => 'Capturar errores de JavaScript en el administrador',
    ],

    'alerts' => [
        'purpose' => 'Alertas de errores',
        'subject' => [
            'new' => 'Error nuevo en :site: :class',
            'regression' => 'Un error ha reaparecido en :site: :class',
            'spike' => 'Pico de errores en :site: :class',
        ],
        'where' => 'Dónde: :place',
        'counts' => 'Eventos: :events, usuarios: :users',
        'release' => 'Versión: :release',
    ],

    'prune' => [
        'invalid' => 'El número de días debe ser 1 o más.',
        'done' => 'Se eliminaron :events eventos y :issues errores con más de :days días.',
    ],

    'test' => [
        'no_drivers' => 'No hay ningún controlador de errores activado.',
        'message' => 'Atrium errors: un error de prueba, se puede resolver sin problema',
        'skipped' => 'No enviado: el seguimiento está desactivado, o el muestreo o el límite por minuto lo descartó.',
        'sent' => 'Error de prueba :id enviado a: :drivers.',
    ],
];
