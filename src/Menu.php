<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors;

use Atrium\Errors\Http\Middleware\AuthorizeErrors;

/**
 * The "Site health" group of the admin sidebar, shared with the monitoring
 * module: whichever comes first creates the group (marked `key` =>
 * `site-health`), the other adds its entries to it. Each module carries the
 * group's label, so either works alone.
 */
final class Menu
{
    public const GROUP = 'site-health';

    public const ROUTE = 'admin.errors.index';

    /**
     * Left out for an administrator who may not open it.
     *
     * @param  array<int,array<string,mixed>>  $menu
     * @return array<int,array<string,mixed>>
     */
    public static function add(array $menu, mixed $user = null): array
    {
        if (($user !== null && ! AuthorizeErrors::allows($user)) || str_contains((string) json_encode($menu), '"'.self::ROUTE.'"')) {
            return $menu;
        }

        $entry = ['label' => 'atrium-errors::errors.title', 'route' => self::ROUTE, 'icon' => 'info'];

        foreach ($menu as $index => $item) {
            if (($item['key'] ?? null) === self::GROUP) {
                $menu[$index]['children'] = [...($item['children'] ?? []), $entry];

                return $menu;
            }
        }

        $group = ['key' => self::GROUP, 'label' => 'atrium-errors::errors.health', 'icon' => 'chart', 'children' => [$entry]];

        // Before "Modules", which stays last.
        $modules = array_search('admin.modules.index', array_map(static fn (array $item): mixed => $item['route'] ?? null, $menu), true);

        if ($modules === false) {
            $menu[] = $group;
        } else {
            array_splice($menu, (int) $modules, 0, [$group]);
        }

        return $menu;
    }
}
