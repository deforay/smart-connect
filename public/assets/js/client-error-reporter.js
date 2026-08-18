/**
 * Browser error reporter.
 *
 * Captures uncaught script errors and unhandled promise rejections and posts
 * them to the server, where they are written to client-<date>.log and read in
 * the log viewer. Without this a JavaScript failure is visible only to whoever
 * happened to be on the page.
 *
 * Load this in the head, ahead of every other script. A handler registered
 * after the script that fails never hears about it.
 *
 * The page sets window.SC_CLIENT_ERROR_ENDPOINT before loading this file,
 * because a sub-directory deployment does not serve the endpoint from the
 * domain root.
 */
(function() {
    var endpoint = window.SC_CLIENT_ERROR_ENDPOINT || '/client-error/';
    var seen = {};
    var sent = 0;

    // A broken page can throw the same error on every frame or every keystroke.
    // Repeats inside a minute, and anything past the cap, are dropped rather
    // than sent.
    var DEDUPE_MS = 60000;
    var MAX_REPORTS = 20;

    function report(payload) {
        if (sent >= MAX_REPORTS) {
            return;
        }

        var now = new Date().getTime();
        var key = [payload.message, payload.source, payload.line, payload.column].join('|');

        if (seen[key] && now - seen[key] < DEDUPE_MS) {
            return;
        }

        seen[key] = now;
        sent++;
        payload.url = window.location.href;

        var body = JSON.stringify(payload);

        // A beacon survives the page unload that often follows an error. Where
        // it is unavailable, fetch with keepalive does the same job.
        try {
            if (navigator.sendBeacon && navigator.sendBeacon(endpoint, new Blob([body], {
                    type: 'application/json'
                }))) {
                return;
            }
        } catch (e) {}

        try {
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: body,
                keepalive: true
            })['catch'](function() {});
        } catch (e) {}
    }

    window.addEventListener('error', function(e) {
        if (!e) {
            return;
        }

        // A failed image or stylesheet fires this event too, with the element
        // as the target and no message. Those are not errors anyone can act on
        // from a log.
        if (e.target && e.target !== window && e.target.tagName) {
            return;
        }

        report({
            level: 'error',
            message: String(e.message || 'Unknown script error'),
            source: String(e.filename || ''),
            line: e.lineno || null,
            column: e.colno || null,
            stack: e.error && e.error.stack ? String(e.error.stack) : ''
        });
    }, true);

    window.addEventListener('unhandledrejection', function(e) {
        var reason = e && e.reason;
        var message;

        // A rejected API call is usually an object, and an object stringifies
        // to "[object Object]", which says nothing in a log. Encode those
        // instead.
        if (reason === null || reason === undefined) {
            message = 'Unhandled promise rejection';
        } else if (typeof reason === 'string') {
            message = reason;
        } else if (reason.message) {
            message = reason.message;
        } else {
            try {
                message = JSON.stringify(reason);
            } catch (err) {
                message = String(reason);
            }
        }

        report({
            level: 'error',
            message: 'Unhandled rejection: ' + String(message),
            stack: reason && reason.stack ? String(reason.stack) : ''
        });
    });

    // For a catch block that handles its own error but still wants it recorded.
    window.reportClientError = function(error, note) {
        report({
            level: 'error',
            message: (note ? note + ': ' : '') + String(error && error.message ? error.message : error),
            stack: error && error.stack ? String(error.stack) : ''
        });
    };
})();
