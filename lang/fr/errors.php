<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'title' => 'Erreurs',
    'health' => 'État du site',
    'subtitle' => 'Les erreurs du site et de ses pages, une ligne par bug',

    'statuses' => [
        'new' => 'Non résolues',
        'resolved' => 'Résolues',
        'ignored' => 'Ignorées',
        'all' => 'Toutes',
    ],

    'levels' => [
        'fatal' => 'Fatale',
        'error' => 'Erreur',
        'warning' => 'Avertissement',
        'info' => 'Info',
    ],

    'kinds' => [
        'php' => 'PHP',
        'job' => 'Tâche de file d\'attente',
        'schedule' => 'Tâche planifiée',
        'browser' => 'Navigateur',
        'message' => 'Message',
    ],

    'drivers' => [
        'database' => 'Base de données du site (les écrans d\'administration)',
        'sentry' => 'Sentry ou GlitchTip',
        'log' => 'Journal de l\'application',
    ],

    'list' => [
        'search' => 'Rechercher par classe, message ou lieu',
        'site' => 'Site',
        'all_sites' => 'Tous les sites',
        'level' => 'Niveau',
        'any_level' => 'Tous les niveaux',
        'kind' => 'Source',
        'any_kind' => 'Toutes les sources',
        'status' => 'Statut',
        'release' => 'Version',
        'any_release' => 'Toutes les versions',
        'path' => 'Adresse',
        'path_placeholder' => '/catalog',
        'sort' => 'Tri',
        'sorts' => [
            'last_seen_at' => 'Dernière apparition',
            'first_seen_at' => 'Première apparition',
            'times_seen' => 'Plus d\'événements',
            'users_seen' => 'Plus d\'utilisateurs',
        ],
        'filter' => 'Afficher',
        'reset' => 'Réinitialiser',
        'empty' => 'Aucune erreur ici. Bonne nouvelle.',
        'issue' => 'Erreur',
        'events' => 'Événements',
        'users' => 'Utilisateurs',
        'last_seen' => 'Dernière apparition',
        'first_seen' => 'Première apparition',
    ],

    'issue' => [
        'back' => 'Toutes les erreurs',
        'resolve' => 'Résoudre',
        'resolve_next' => 'Résoudre dans la prochaine version',
        'resolve_next_hint' => 'Elle ne revient que si elle se produit dans une version postérieure à :release.',
        'ignore' => 'Ignorer',
        'reopen' => 'Rouvrir',
        'trace' => 'Pile d\'appels',
        'caused_by' => 'Causée par',
        'all_frames' => 'Afficher les appels du framework',
        'app_frames' => 'Masquer les appels du framework',
        'request' => 'Requête',
        'context' => 'Contexte',
        'breadcrumbs' => 'Avant l\'erreur',
        'environment' => 'Environnement',
        'occurrences' => 'Occurrences',
        'frequency' => 'Fréquence',
        'last_24h' => '24 heures',
        'last_30d' => '30 jours',
        'top_paths' => 'Adresses',
        'top_releases' => 'Versions',
        'first_release' => 'Première version',
        'last_release' => 'Dernière version',
        'resolved_in' => 'Résolue dans la version :release',
        'regressed' => 'Réapparue',
        'no_event' => 'Aucun événement n\'est conservé pour cette erreur : ils dépassent la durée de conservation.',
        'open' => 'Ouvrir',
        'shown' => 'Affichée',
        'time' => 'Heure',
        'user' => 'Utilisateur',
        'method' => 'Méthode',
        'url' => 'Adresse',
        'route' => 'Route',
        'site' => 'Site',
        'locale' => 'Langue',
        'release' => 'Version',
        'php' => 'PHP',
        'laravel' => 'Laravel',
        'none' => '—',
    ],

    'status_saved' => [
        'new' => 'L\'erreur est de nouveau ouverte.',
        'resolved' => 'L\'erreur est résolue.',
        'ignored' => 'L\'erreur est ignorée.',
    ],

    'gadget' => [
        'title' => 'Erreurs sur 24 heures',
        'events' => 'événements',
        'new' => 'nouvelles',
        'regressed' => 'réapparues',
        'unresolved' => 'non résolues',
        'none' => 'Aucune erreur ces dernières 24 heures.',
        'top' => 'Les plus bruyantes',
        'all' => 'Toutes les erreurs',
        'chart' => 'Événements par heure',
    ],

    'permissions' => [
        'view' => 'Voir les erreurs',
    ],

    'settings' => [
        'description' => 'Où les erreurs sont stockées et envoyées, en quelle quantité, et qui est prévenu. Un champ vide prend la valeur de config/atrium-errors.php ou .env ; gardez-y les secrets.',
        'drivers' => 'Envoyer les erreurs vers',
        'drivers_hint' => 'Plusieurs à la fois. Les écrans d\'administration et les alertes ont besoin de la base de données du site.',
        'sentry_dsn' => 'DSN Sentry',
        'sentry_dsn_hint' => 'Fourni par Sentry, GlitchTip ou un autre service compatible Sentry : https://clé@hôte/projet. Mieux dans .env sous ATRIUM_ERRORS_SENTRY_DSN.',
        'sample_rate' => 'Conserver, %',
        'sample_rate_hint' => 'Part des événements conservés, de 1 à 100.',
        'per_minute' => 'Maximum par minute',
        'per_minute_hint' => 'Les événements au-delà de ce seuil en une minute sont ignorés. 0 : aucune limite.',
        'retention_days' => 'Conserver les événements, jours',
        'alert_email' => 'E-mails d\'alerte',
        'alert_email_hint' => 'Adresses séparées par des virgules.',
        'telegram_token' => 'Jeton du bot Telegram',
        'telegram_token_hint' => 'Fourni par @BotFather. Mieux dans .env sous ATRIUM_ERRORS_TELEGRAM_TOKEN.',
        'telegram_chat' => 'ID du chat Telegram',
        'alert_new' => 'Alerter pour une nouvelle erreur',
        'alert_regression' => 'Alerter quand une erreur résolue réapparaît',
        'spike_per_hour' => 'Pic : événements d\'une erreur par heure',
        'spike_per_hour_hint' => 'Au-delà de ce nombre en une heure, une alerte est envoyée. 0 : désactivé.',
        'browser_site' => 'Capturer les erreurs JavaScript sur le site',
        'browser_admin' => 'Capturer les erreurs JavaScript dans l\'administration',
    ],

    'alerts' => [
        'purpose' => 'Alertes d\'erreurs',
        'subject' => [
            'new' => 'Nouvelle erreur sur :site : :class',
            'regression' => 'Une erreur est réapparue sur :site : :class',
            'spike' => 'Pic d\'erreurs sur :site : :class',
        ],
        'where' => 'Où : :place',
        'counts' => 'Événements : :events, utilisateurs : :users',
        'release' => 'Version : :release',
    ],

    'prune' => [
        'invalid' => 'Le nombre de jours doit être d\'au moins 1.',
        'done' => ':events événements et :issues erreurs de plus de :days jours supprimés.',
    ],

    'test' => [
        'no_drivers' => 'Aucun pilote d\'erreurs n\'est activé.',
        'message' => 'Atrium errors: une erreur de test, à résoudre sans crainte',
        'skipped' => 'Non envoyée : le suivi est désactivé, ou l\'échantillonnage ou la limite par minute l\'a écartée.',
        'sent' => 'Erreur de test :id envoyée à : :drivers.',
    ],
];
