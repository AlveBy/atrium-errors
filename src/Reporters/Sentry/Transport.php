<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Reporters\Sentry;

/**
 * Delivers an envelope to a Sentry-compatible service. The default goes over
 * HTTP; tests bind a fake that keeps the envelopes.
 */
interface Transport
{
    /**
     * @param  array<string,mixed>  $event  the event in the Sentry format
     */
    public function send(Dsn $dsn, array $event): void;
}
