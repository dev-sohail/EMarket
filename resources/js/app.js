// Project JavaScript
//
// All interactive behaviour lives here rather than in inline <script> blocks or
// on* attributes. The app ships a nonce-based CSP with no 'unsafe-inline' and
// no 'unsafe-eval' (app/Http/Middleware/SecurityHeadersMiddleware.php), so
// inline scripts and inline event handlers are both blocked at the browser.
// Bundled assets are served from 'self', which the policy allows.
//
// Elements opt in with data-* attributes and are handled by one delegated
// listener each, so markup can be rendered in a loop without re-binding.

window.showToast = function (message, type = 'success') {
    const toast = document.createElement('div');
    const color = type === 'error'
        ? 'bg-[#D6001C]'
        : type === 'info'
            ? 'bg-[#2563eb]'
            : type === 'warning'
                ? 'bg-[#D97706]'
                : 'bg-[#1B7A43]';

    toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 text-white ${color}`;
    toast.textContent = message;

    // Start hidden, then animate in on the next frame.
    toast.style.transition = 'opacity .3s ease, transform .3s ease';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    });

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
};

const metaContent = (name) =>
    document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

// ---------------------------------------------------------------------------
// Cart + wishlist
// ---------------------------------------------------------------------------

function updateCartCount() {
    const url = metaContent('cart-count-url');
    if (!url) return;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const count = data.data?.count ?? data.count ?? 0;
            document.querySelectorAll('[data-cart-count]').forEach(el => {
                el.textContent = count;
            });
        })
        .catch(() => {});
}

function addToCart(productId, url) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ product_id: productId, quantity: 1 }),
    })
        .then(r => {
            if (r.status === 419) {
                showToast('Session expired. Please refresh the page and try again.', 'error');
                throw new Error('session expired');
            }
            return r.json();
        })
        .then(data => {
            if (data && data.success) {
                updateCartCount();
                showToast('Added to cart!');
            } else if (data) {
                showToast(data.message || 'Failed to add to cart', 'error');
            }
        })
        .catch(() => {});
}

function addToWishlist(productId, url, loginUrl) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ product_id: productId }),
    })
        .then(r => {
            if (r.status === 401) {
                showToast('Please login to use wishlist', 'error');
                window.location.href = loginUrl;
                throw new Error('unauthenticated');
            }
            return r.json();
        })
        .then(data => {
            showToast(data.message || (data.success ? 'Done' : 'Failed'), data.success ? 'success' : 'error');
        })
        .catch(() => {});
}

function togglePanel(panelId) {
    document.getElementById(panelId)?.classList.toggle('hidden');
}

function updateValueHelper() {
    var helper = this.form?.querySelector('[data-value-helper]');
    if (!helper) return;

    helper.textContent = this.value === 'fixed'
        ? (helper.dataset.fixedText || '')
        : (helper.dataset.pctText || '');
}

function removeWishlistItem(productId, url, el) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ product_id: productId }),
    })
        .then(r => r.json())
        .then(data => {
            el.closest('[data-wishlist-item]')?.remove();
            showToast(data.message || 'Removed from wishlist');
        })
        .catch(() => {});
}

// ---------------------------------------------------------------------------
// Delegated click handling
// ---------------------------------------------------------------------------

document.addEventListener('click', function (event) {
    const target = event.target.closest('[data-action], [data-toggle], [data-add-to-cart], [data-add-to-wishlist]');

    if (!target) return;

    if (target.dataset.toggle) {
        event.preventDefault();
        togglePanel(target.dataset.toggle);
        return;
    }

    const { action, productId } = target.dataset;

    switch (action) {
        case 'add-to-cart':
            event.preventDefault();
            addToCart(productId, target.dataset.url);
            return;

        case 'add-to-wishlist':
            event.preventDefault();
            addToWishlist(productId, target.dataset.url, target.dataset.loginUrl);
            return;

        case 'remove-self':
            event.preventDefault();
            target.closest('[data-removable]')?.remove();
            return;

        case 'remove-wishlist':
            event.preventDefault();
            removeWishlistItem(productId, target.dataset.url, target);
            return;

        case 'move-to-cart':
            event.preventDefault();
            addToCart(productId, target.dataset.cartUrl).then(() => {
                target.closest('[data-wishlist-item]')?.remove();
            });
            return;

        case 'print':
            event.preventDefault();
            window.print();
            return;

        case 'copy-text': {
            event.preventDefault();
            const text = target.dataset.copyText ?? '';
            const original = target.dataset.copyLabel ?? 'Copy Link';

            navigator.clipboard.writeText(text).then(() => {
                target.textContent = 'Copied!';
                setTimeout(() => { target.textContent = original; }, 1500);
            }).catch(() => {
                showToast('Could not copy to clipboard', 'error');
            });
            return;
        }

        case 'select-text':
            event.preventDefault();
            target.select();
            return;

        default:
            break;
    }

    // Generic dispatcher, shared with the `change` listener below.
    dispatchAction(target, event);
});

// Resolve `data-args` and invoke the named global function.
//
//   <button data-action="updateQuantity" data-args="['7', 2]">
//   <button data-action="openVerifyModal" data-args="['7', 'ORD-1']">
//   <input  data-action="updateQuantity" data-args="['7', null]" data-arg-value="1">
//
// Arguments travel as JSON so numbers stay numbers and strings stay strings,
// which bare attribute values would not preserve. `data-arg-value="n"` instead
// takes argument n from the element's own value and coerces it to a number when
// it is numeric, which covers <select>/<input> handlers that used `this.value`.
function dispatchAction(target, event) {
    const { action } = target.dataset;

    if (!action || typeof window[action] !== 'function') return false;

    let args = [];

    if (target.dataset.args) {
        try {
            args = JSON.parse(target.dataset.args);
        } catch {
            return false;
        }
    }

    if (!Array.isArray(args)) return false;

    const valueIndex = target.dataset.argValue;

    if (valueIndex !== undefined) {
        const index = Number(valueIndex);
        const raw = target.value;
        const numeric = Number(raw);

        args[index] = raw !== '' && !Number.isNaN(numeric) ? numeric : raw;
    }

    event.preventDefault();
    window[action].apply(target, args);

    return true;
}

// Auto-submit: <select data-auto-submit> submits its form on change, replacing
// the old onchange="this.form.submit()". Page functions that used to hang off
// onchange="someFn()" run through the shared dispatcher.
document.addEventListener('change', function (event) {
    const el = event.target.closest('[data-auto-submit], [data-goto], [data-action]');

    if (!el) return;

    if (el.hasAttribute('data-auto-submit')) {
        el.form?.submit();
        return;
    }

    if (el.hasAttribute('data-goto')) {
        if (el.value) {
            window.location.href = el.value;
        }
        return;
    }

    dispatchAction(el, event);
});

// ---------------------------------------------------------------------------
// Confirm-on-submit (replaces inline onsubmit="return confirm(...)")
// ---------------------------------------------------------------------------

document.addEventListener('submit', function (event) {
    const form = event.target.closest('[data-confirm]');

    if (!form) return;

    if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

// ---------------------------------------------------------------------------
// Alpine components
//
// Loaded from @alpinejs/csp, which parses expressions with an interpreter
// rather than new Function(). That build rejects arrow functions, property
// assignment and template literals inside attributes, so any non-trivial logic
// lives here as a registered component instead.
// ---------------------------------------------------------------------------

document.addEventListener('alpine:init', function () {
    window.Alpine.data('popup', function () {
        return {
            open: false,

            get cookieKey() { return this.$el.dataset.cookieKey ?? ''; },
            get popupId() { return this.$el.dataset.popupId ?? ''; },
            get delay() { return parseInt(this.$el.dataset.delay ?? '0', 10); },
            get enableCookie() { return this.$el.dataset.enableCookie === '1'; },
            get showOnlyOnce() { return this.$el.dataset.showOnlyOnce === '1'; },

            init() {
                if (this.wasAlreadyShown()) {
                    this.open = false;
                    return;
                }

                this.timer = setTimeout(() => { this.open = true; }, this.delay);
            },

            wasAlreadyShown() {
                if (this.cookieKey && document.cookie.split(';').some(c => c.trim().startsWith(this.cookieKey + '='))) {
                    return true;
                }

                return this.showOnlyOnce && sessionStorage.getItem('popup_' + this.popupId) !== null;
            },

            setCookie() {
                if (!this.enableCookie || !this.cookieKey) return;
                document.cookie = this.cookieKey + '=1;max-age=' + (this.cookieDays * 24 * 60 * 60) + ';path=/';
            },

            get cookieDays() {
                return parseInt(this.$el.dataset.cookieDays ?? '7', 10);
            },

            setSession() {
                if (!this.showOnlyOnce || !this.popupId) return;
                sessionStorage.setItem('popup_' + this.popupId, '1');
            },

            dismiss() {
                clearTimeout(this.timer);
                this.setCookie();
                this.setSession();
                this.open = false;
            },

            close() {
                this.dismiss();
            },
        };
    });
});

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', function () {
    updateCartCount();

    // Auto-hide flash messages
    document.querySelectorAll('.flash-message').forEach(el => {
        setTimeout(() => el.remove(), 5000);
    });
});

// ---------------------------------------------------------------------------
// Settings page — sticky section navigation
// ---------------------------------------------------------------------------

function setupSettingsNav() {
    const links = document.querySelectorAll('.nav-link');
    const sections = Array.from(links)
        .map((link) => document.querySelector(link.getAttribute('href')))
        .filter(Boolean);

    if (!sections.length) {
        return;
    }

    const activate = (id) => {
        links.forEach((l) => l.classList.toggle('active', l.getAttribute('href') === '#' + id));
    };

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    activate(entry.target.id);
                }
            });
        },
        { rootMargin: '-40% 0px -55% 0px', threshold: 0 }
    );

    sections.forEach((section) => observer.observe(section));

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            const id = link.getAttribute('href').slice(1);
            const target = document.getElementById(id);
            if (target) {
                event.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

if (document.querySelector('.nav-link')) {
    setupSettingsNav();
}

/**
 * Theme color picker sync: each colour input sits transparently on top of its
 * hex label, so the label and the preview swatch must mirror the picker on
 * change. The markup is rendered by admin/setting/index.blade.php as
 * <div class="relative flex-1"><input type="color" name="color_x">…
 * <span class="color-hex">#rrggbb</span></div> followed by the swatch div.
 */
function setupColorPickers() {
    const pickers = document.querySelectorAll('input[type="color"][name^="color_"]');

    pickers.forEach((picker) => {
        const wrap = picker.parentElement;
        const label = wrap?.querySelector('.color-hex');
        const swatch = wrap?.nextElementSibling?.firstElementChild;

        const sync = () => {
            if (label) label.textContent = picker.value.toUpperCase();
            if (swatch) swatch.style.backgroundColor = picker.value;
        };

        picker.addEventListener('input', sync);
        picker.addEventListener('change', sync);
    });
}

if (document.querySelector('input[name="color_ink"]')) {
    setupColorPickers();
}

// ---------------------------------------------------------------------------
// Analytics real-time refresh
// ---------------------------------------------------------------------------
//
// The real-time tab used a <meta http-equiv="refresh"> to poll every 30s. That
// tag was emitted inside @section('header'), which layouts.admin yields inside
// <main>, and browsers only honour a refresh meta in <head> — so the page never
// actually reloaded. An explicit button is predictable, and the query string is
// preserved so the selected period survives the reload.

function reloadAnalyticsPage() {
    window.location.reload();
}

window.reloadAnalyticsPage = reloadAnalyticsPage;

window.updateCartCount = updateCartCount;
window.updateValueHelper = updateValueHelper;