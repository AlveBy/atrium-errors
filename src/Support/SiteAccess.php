<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Support;

use Atrium\Admin\Models\AdminUser;

/**
 * Which sites' errors the signed-in administrator may see. An administrator
 * kept to some sites (the "Sites" field of the account) sees only the events
 * of those sites; errors that belong to no site (a console command, a queue
 * job) are for the administrators who may see every site.
 */
final class SiteAccess
{
    /**
     * The site ids, or null for every site and the errors outside them.
     *
     * @return list<int>|null
     */
    public static function ids(): ?array
    {
        $user = auth((string) config('atrium.auth.guard', 'admin'))->user();

        if (! $user instanceof AdminUser || (array) $user->site_ids === []) {
            return null;
        }

        return $user->allowedSites()->map(static fn ($site): int => $site->id)->values()->all();
    }
}
