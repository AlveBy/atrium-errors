<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Http\Middleware;

use Atrium\Admin\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Error traces show code and addresses: only administrators with "View
 * errors" in their role see them (a superadmin always). The module's
 * settings are a separate permission, checked by the core, so support can
 * read and triage errors without changing where they are sent. The core
 * opens routes outside its resources to every administrator, hence this
 * check.
 */
final class AuthorizeErrors
{
    public const PERMISSION = 'errors.view';

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::allows($request->user((string) config('atrium.auth.guard', 'admin')))) {
            abort(403, __('atrium::permissions.forbidden'));
        }

        return $next($request);
    }

    public static function allows(mixed $user): bool
    {
        return $user instanceof AdminUser && $user->hasAdminPermission(self::PERMISSION);
    }
}
