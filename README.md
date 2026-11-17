# Atrium Errors

Error tracking for the [Atrium](https://github.com/AlveBy/atrium) admin, kept in the site's own database — a small Sentry inside the site:

- PHP exceptions, failed queue jobs, failed scheduled tasks and JavaScript errors of visitors' browsers;
- grouped by class and place, so one bug is one line, with a status that comes back by itself when a fixed bug happens again;
- **Site health → Errors**: the list with filters, and each error with its trace and code, requests, a chart and every event; **Resolve in next release**; a dashboard tile;
- e-mail and Telegram alerts for new errors, errors that came back and spikes, throttled;
- secrets and personal data scrubbed before anything is stored; sampling, a per-minute cap, writing after the response, a fallback log, 90 days kept;
- the same events to Sentry or GlitchTip with only a DSN, to the log, or to your own driver (Bugsnag, Rollbar, a webhook) through one small interface.

## Install

```bash
composer require alveby/atrium-errors
php artisan atrium:module:install alveby/atrium-errors
php artisan atrium:errors:test
```

To send errors to Sentry or GlitchTip as well:

```dotenv
ATRIUM_ERRORS_DRIVERS=database,sentry
ATRIUM_ERRORS_SENTRY_DSN=https://public-key@errors.example.com/7
```

Seeing errors takes the `errors.view` right, changing the settings `errors.settings`. An administrator of some sites sees only their errors.

Everything else — drivers, your own driver, alerts, the browser script, what is scrubbed, settings: [docs/errors.md](https://github.com/AlveBy/atrium/blob/main/docs/errors.md).

## License

MIT
