// Error pages only.
//
// Deliberately separate from resources/js/app.js: the full bundle boots cart
// and admin handlers on DOMContentLoaded, none of which belong on a 403 or a
// 500. This file is the whole of the error-page behaviour, loaded only by
// resources/views/errors/partials/shell.blade.php.
//
// Served as an external file, so it needs no CSP nonce (script-src is
// 'self' + nonce, with no 'unsafe-inline').

document.addEventListener('click', function (event) {
    const el = event.target.closest('[data-action]');

    if (!el) return;

    switch (el.dataset.action) {
        case 'reload':
            event.preventDefault();
            window.location.reload();
            break;

        case 'history-back':
            event.preventDefault();
            // A cold direct hit on the error URL has nothing to go back to;
            // leave the visitor where they are rather than bouncing them out.
            if (window.history.length > 1) {
                window.history.back();
            }
            break;
    }
});