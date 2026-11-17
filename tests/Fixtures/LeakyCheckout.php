<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Tests\Fixtures;

use RuntimeException;

/**
 * Code with secrets written in it, the way real code sometimes is: the lines
 * around the throw end up in the trace.
 */
final class LeakyCheckout
{
    public function pay(): never
    {
        $password = 'Tr0ub4dor&3';
        $customer = 'olga.petrova@example.com';
        $phone = '+375 29 123-45-67';
        throw new RuntimeException('Payment refused for '.$customer.' with password='.$password);
    }
}
