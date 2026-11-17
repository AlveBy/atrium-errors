<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Models;

use Atrium\Errors\Support\SiteAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One time an issue happened, with the scrubbed event in `payload`.
 *
 * @property int $id
 * @property int $issue_id
 * @property int|null $site_id
 * @property string $level
 * @property string|null $release
 * @property string|null $environment
 * @property string|null $method
 * @property string|null $path
 * @property string|null $route
 * @property string|null $locale
 * @property int|null $user_id
 * @property array<string,mixed> $payload
 * @property Carbon|null $created_at
 */
final class Occurrence extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'error_events';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'site_id' => 'integer',
            'user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Only the events of the sites the administrator may see.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeVisible(Builder $query): Builder
    {
        $sites = SiteAccess::ids();

        return $sites === null ? $query : $query->whereIn($this->qualifyColumn('site_id'), $sites);
    }
}
