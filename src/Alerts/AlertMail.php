<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Errors\Alerts;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The alert as a plain-text letter. Marked with the "errors" purpose, so the
 * mail module (alveby/atrium-mail), when installed, sends it through the
 * account chosen for error alerts.
 */
final class AlertMail extends Mailable
{
    public function __construct(public readonly Alert $alert)
    {
        $this->metadata('atrium_purpose', 'errors');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alert->subject());
    }

    public function content(): Content
    {
        return new Content(text: 'atrium-errors::mail.alert', with: ['text' => $this->alert->text()]);
    }
}
