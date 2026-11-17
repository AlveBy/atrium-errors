/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

/*
 * Reports JavaScript errors of the page to the site, at most five per page
 * and never the same one twice. Sends only the error itself: the message,
 * its type and the stack. The server adds the page address and scrubs it.
 */
(function () {
    var script = document.currentScript;
    if (!script || !window.JSON) {
        return;
    }

    var endpoint = script.getAttribute('data-endpoint');
    var source = script.getAttribute('data-source') || 'site';
    var sent = {};
    var left = 5;

    function send(report) {
        var key = report.message + '|' + report.url + '|' + report.line;
        if (!endpoint || left <= 0 || sent[key]) {
            return;
        }
        sent[key] = true;
        left--;

        var body = JSON.stringify(report).slice(0, 16000);
        try {
            if (navigator.sendBeacon && navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }))) {
                return;
            }
            fetch(endpoint, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } });
        } catch (e) {
            // Reporting must never break the page.
        }
    }

    function fromError(error, fallback) {
        error = error || {};
        return {
            message: String(error.message || fallback.message || 'Unknown error').slice(0, 1000),
            name: String(error.name || 'Error').slice(0, 64),
            stack: String(error.stack || '').slice(0, 10000),
            url: String(fallback.url || ''),
            line: fallback.line || 0,
            column: fallback.column || 0,
            source: source
        };
    }

    window.addEventListener('error', function (event) {
        // A failed <img> or <script> load is not a script error.
        if (!event.message && !event.error) {
            return;
        }
        send(fromError(event.error, { message: event.message, url: event.filename, line: event.lineno, column: event.colno }));
    });

    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        send(fromError(reason instanceof Error ? reason : null, { message: 'Unhandled rejection: ' + String(reason) }));
    });
})();
