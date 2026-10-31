<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            $settings = Cache::remember('settings.all', now()->addMinutes(5), function () {
                return Setting::query()
                    ->get()
                    ->pluck('value', 'key')
                    ->all();
            });
        } catch (Throwable $e) {
            Log::warning('settings layer failed: '.$e->getMessage());

            return $default;
        }

        return $settings[$key] ?? $default;
    }
}

if (! function_exists('setting_forget')) {
    function setting_forget(): void
    {
        Cache::forget('settings.all');
    }
}

if (! function_exists('affiliate_enabled')) {
    function affiliate_enabled(mixed $default = null): ?bool
    {
        $value = setting('affiliate_enabled');

        return $value === null ? ($default === null ? null : (bool) $default) : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (! function_exists('affiliate_commission_rate')) {
    function affiliate_commission_rate(float $default = 0.0): float
    {
        $value = setting('affiliate_commission_rate');

        return $value === null ? $default : (float) $value;
    }
}

if (! function_exists('affiliate_cookie_days')) {
    function affiliate_cookie_days(int $default = 0): int
    {
        $value = setting('affiliate_cookie_days');

        return $value === null ? $default : (int) $value;
    }
}

if (! function_exists('affiliate_min_payout')) {
    function affiliate_min_payout(float $default = 0.0): float
    {
        $value = setting('affiliate_min_payout');

        return $value === null ? $default : (float) $value;
    }
}

if (! function_exists('currency')) {
    function currency(string $default = ''): string
    {
        return setting('currency', $default);
    }
}

if (! function_exists('currency_symbol')) {
    function currency_symbol(): string
    {
        $currency = currency();

        return match ($currency) {
            'USD' => '$',
            'CHF' => 'Fr.',
            'PKR' => 'Rs.',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => '₹',
            'JPY' => '¥',
            default => $currency,
        };
    }
}

if (! function_exists('format_currency')) {
    function format_currency(?float $amount): string
    {
        $currency = currency();
        $decimalPlaces = in_array($currency, (array) config('app.currencies.zero_decimal', []), true) ? 0 : 2;

        // A nullable money column (e.g. an unset sale price) reaches here as
        // null; a non-nullable float parameter turned that into a TypeError.
        return trim(currency_symbol().' '.number_format((float) $amount, $decimalPlaces));
    }
}

if (! function_exists('inventory_number_setting')) {
    /**
     * Resolve a whole-number inventory setting from the settings table first,
     * then from the env-backed config, normalising "blank" to null.
     *
     * Two traps meet here:
     *
     *  - config/app.php defaults both inventory env vars to '' so a fresh
     *    install ships without them, and `config('…', 5)` does NOT fall back in
     *    that case: the key exists, its value is the empty string. Bound as a
     *    query parameter MySQL coerces '' to 0, so
     *    `where('quantity', '<=', $threshold)->where('quantity', '>', 0)`
     *    matched nothing and every low-stock count was 0.
     *  - The settings screen writes low_stock_threshold to the settings table,
     *    so an operator setting it there changed nothing at all while every
     *    reader looked only at config.
     *
     * @return int|null null means "not configured", which callers must handle
     */
    function inventory_number_setting(string $settingKey, string $configKey): ?int
    {
        foreach ([setting($settingKey), config($configKey)] as $value) {
            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;

            }

            return max(0, (int) $value);
        }

        return null;
    }
}

if (! function_exists('low_stock_threshold')) {
    /**
     * The configured low-stock threshold, or null when it has not been set.
     *
     * Callers must branch on null: an unconfigured threshold means "no
     * low-stock reporting", exactly as DashboardController reports it.
     */
    function low_stock_threshold(): ?int
    {
        return inventory_number_setting('low_stock_threshold', 'app.inventory.low_stock_threshold');
    }
}

if (! function_exists('reservation_ttl_minutes')) {
    /**
     * How long a cart reservation may be held before the hourly
     * app:expire-reservations job releases it, or null when not configured.
     *
     * Null must not be treated as zero: a zero TTL releases every reservation
     * the moment it is created, emptying live carts and putting the released
     * stock back on sale while the shopper is still checking out.
     */
    function reservation_ttl_minutes(): ?int
    {
        return inventory_number_setting('reservation_ttl_minutes', 'app.inventory.reservation_ttl_minutes');
    }
}

if (! function_exists('sanitize_color')) {
    function sanitize_color(?string $color, string $fallback = ''): string
    {
        if ($color && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color)) {
            return $color;

        }

        return $fallback;
    }
}

if (! function_exists('acting_user_id')) {
    /**
     * The id of the user acting in the current authenticated context.
     *
     * Admin routes run on the `admin` guard, while the default `auth()`
     * resolves to the `web` guard — and in an admin session `auth()->id()`
     * is null. Attribution fields (author_id, changed_by, performed_by,
     * enquiry user_id) must consult the admin guard first.
     */
    function acting_user_id(): ?string
    {
        $admin = auth()->guard('admin')->user();

        if ($admin) {
            return $admin->getKey();
        }

        $web = auth()->guard('web')->user();

        return $web?->getKey();
    }
}

if (! function_exists('is_mobile_request')) {
    /**
     * Whether the current request looks like it came from a phone.
     *
     * Used to honour the per-popup `show_on_mobile` / `show_on_desktop` flags.
     * The check is deliberately user-agent based: there is no client-side
     * viewport signal available when the page is rendered, and a popup that is
     * emitted into the DOM only to be hidden by JS still costs the overlay.
     */
    function is_mobile_request(): bool
    {
        $userAgent = request()->userAgent() ?? '';

        return (bool) preg_match(
            '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i',
            $userAgent
        );
    }
}
