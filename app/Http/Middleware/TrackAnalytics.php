<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsPageView;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Services\Install\InstallState;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class TrackAnalytics
{
    /**
     * analytics_page_views.path is char(255); longer paths are truncated
     * rather than written, so a deep link can never overflow the column.
     */
    protected const int MAX_PATH_LENGTH = 255;

    protected int $sessionInactivityMinutes = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldSkip($request)) {
            return $response;
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $response;
        }

        if ($this->isBot($request)) {
            return $response;
        }

        $visitorUuid = $request->cookie('analytics_visitor_uuid');
        $sessionId = $request->cookie('analytics_session_id');

        if (! $visitorUuid || ! AnalyticsVisitor::where('uuid', $visitorUuid)->exists()) {
            $visitorUuid = $this->createOrGetVisitor($request, $visitorUuid);
            $response->withCookie($this->visitorCookie($visitorUuid));
        }

        $now = Carbon::now();

        if (! $sessionId || ! AnalyticsSession::where('session_uuid', $sessionId)->exists()) {
            $sessionId = $this->startSession($visitorUuid);
            $response->withCookie($this->sessionCookie($sessionId));
        }

        $this->updateSession($sessionId, $now);
        $this->recordPageView($sessionId, $visitorUuid, $request, $now);

        return $response;
    }

    /**
     * Paths that must never be recorded.
     *
     * 'admin/*' does not match the bare '/admin' dashboard path, so the
     * back-office landing page was being tracked. The Stripe webhook is listed
     * explicitly: it carries an email, a session id and a payment intent, and
     * it must never become an analytics row.
     */
    protected function shouldSkip(Request $request): bool
    {
        // Check for Do Not Track header or opt-out cookie
        if ($request->header('DNT') === '1' || $request->cookie('analytics_opt_out') === 'true') {
            return true;
        }

        // Nothing to record on an un-installed application, and the tables this
        // writes to do not exist yet.
        //
        // Checked before anything else because handle() runs its analytics work
        // *after* $next() has produced a response: RedirectToInstaller will have
        // turned this request into a 302 to the wizard, but this middleware still
        // executes its own logic on the way back out. Without this guard that
        // logic queries analytics_visitors and the visitor gets a 500 instead of
        // the redirect.
        //
        // Resolved directly rather than through a bound() check: InstallState has
        // no explicit binding, and Container::bound() returns false for a class
        // that is merely auto-resolvable, which would skip this guard entirely and
        // silently reintroduce the 500.
        if (app(InstallState::class)->needsInstall()) {
            return true;
        }

        return $request->is('admin', 'admin/*')
            || $request->is('stripe/webhook')
            || $request->is('login', 'register', 'cart')
            || $request->is('checkout*', 'checkout/*')
            || $request->ajax()
            || $request->wantsJson()
            || $request->is('api/*')
            // The installer writes an administrator account and a database
            // password. Recording it as an analytics row would put the installer's
            // path and referrer into a table the same wizard is about to create,
            // and this middleware queries analytics_visitors — which does not
            // exist yet on an un-installed application.
            || $request->is('install', 'install/*')
            || $request->is('storage/*', 'vendor/*')
            || str_starts_with($request->path(), '_dusk_');
    }

    protected function isBot(Request $request): bool
    {
        $userAgent = $request->userAgent() ?? '';

        $botPatterns = config('analytics.bot_user_agents', [
            'bot', 'crawl', 'spider', 'slurp', 'bingbot', 'googlebot',
            'facebookexternalhit', 'twitterbot', 'applebot', 'linkedinbot',
            'semrushbot', 'ahrefsbot', 'mj12bot', 'yandexbot', 'baidubot',
            'duckduckbot', 'telegrambot', 'whatsapp',
        ]);

        $userAgentLower = strtolower($userAgent);

        foreach ($botPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function createOrGetVisitor(Request $request, ?string $existingUuid): string
    {
        if ($existingUuid && AnalyticsVisitor::where('uuid', $existingUuid)->exists()) {
            return $existingUuid;
        }

        $visitorUuid = $existingUuid ?? Str::uuid()->toString();

        AnalyticsVisitor::create([
            'uuid' => $visitorUuid,
            'country' => $this->resolveCountry($request),
            'device_type' => $this->resolveDeviceType($request),
            'browser' => $this->resolveBrowser($request),
            'referrer_domain' => $this->resolveReferrerDomain($request),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return $visitorUuid;
    }

    protected function resolveCountry(Request $request): ?string
    {
        if (! app()->isLocal() && $request->server('HTTP_CF_IPCOUNTRY')) {
            $country = $request->server('HTTP_CF_IPCOUNTRY');

            if (strlen($country) === 2) {
                return strtoupper($country);
            }
        }

        return null;
    }

    protected function resolveDeviceType(Request $request): ?string
    {
        $userAgent = $request->userAgent() ?? '';

        if (preg_match('/(tablet|ipad|playbook|silk)/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/(mobile|android|iphone|ipod|opera mini|mobile.+firefox)/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected function resolveBrowser(Request $request): ?string
    {
        $userAgent = $request->userAgent() ?? '';

        if (str_contains($userAgent, 'Edg')) {
            return 'Edge';
        }

        if (str_contains($userAgent, 'Firefox')) {
            return 'Firefox';
        }

        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome';
        }

        if (str_contains($userAgent, 'Safari')) {
            return 'Safari';
        }

        return null;
    }

    /**
     * Store only the referring host — never the referring URL, which carries
     * its own query string (campaign ids, email addresses, session tokens).
     */
    protected function resolveReferrerDomain(Request $request): ?string
    {
        $referrer = $request->server('HTTP_REFERER');

        if (! $referrer) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        return $host ? Str::limit($host, 255, '') : null;
    }

    protected function startSession(string $visitorUuid): string
    {
        $sessionId = Str::uuid()->toString();
        $visitorId = AnalyticsVisitor::where('uuid', $visitorUuid)->value('_id');

        AnalyticsSession::create([
            'visitor_id' => $visitorId,
            'session_uuid' => $sessionId,
            'started_at' => now(),
            'ended_at' => now()->addMinutes($this->sessionInactivityMinutes),
            'page_view_count' => 1,
            'duration_seconds' => 0,
            'bounced' => false,
        ]);

        return $sessionId;
    }

    protected function updateSession(string $sessionId, Carbon $now): void
    {
        $session = AnalyticsSession::where('session_uuid', $sessionId)->first();

        if (! $session) {
            return;
        }

        $inactivityThreshold = $now->copy()->subMinutes($this->sessionInactivityMinutes);

        if (Carbon::parse($session->ended_at)->lt($inactivityThreshold)) {
            $session->update([
                'ended_at' => $now->addMinutes($this->sessionInactivityMinutes),
            ]);
        } else {
            $session->increment('page_view_count');
            $session->update([
                'ended_at' => $now->addMinutes($this->sessionInactivityMinutes),
            ]);
        }

        if ($session->visitor_id) {
            AnalyticsVisitor::where('_id', $session->visitor_id)
                ->where('last_seen_at', '<', $now)
                ->update(['last_seen_at' => $now]);
        }
    }

    protected function recordPageView(string $sessionId, string $visitorUuid, Request $request, Carbon $now): void
    {
        $visitorId = AnalyticsVisitor::where('uuid', $visitorUuid)->value('_id');

        AnalyticsPageView::create([
            'visitor_id' => $visitorId,
            'session_uuid' => $sessionId,
            'path' => $this->sanitizePath($request->path()),
            'title' => null,
            'viewed_at' => $now,
            'duration_seconds' => 0,
        ]);
    }

    /**
     * Strip any query string, redact PII-shaped path segments (emails, phone
     * numbers, in raw or percent-encoded form) and bound the length, so
     * analytics storage never retains personal data or overflows the column.
     */
    protected function sanitizePath(string $path): string
    {
        $path = strtok($path, '?') ?: $path;

        $segments = array_map(function (string $segment) {
            $decoded = rawurldecode($segment);

            if (preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $decoded)) {
                return '[redacted]';
            }

            if (preg_match('/^\+?[0-9][0-9\-\s()]{6,}[0-9]$/', $decoded)) {
                return '[redacted]';
            }

            return $segment;
        }, explode('/', $path));

        return Str::limit(implode('/', $segments), self::MAX_PATH_LENGTH, '');
    }

    protected function visitorCookie(string $uuid): Cookie
    {
        // httpOnly=true prevents XSS access; secure=true for HTTPS only
        return cookie('analytics_visitor_uuid', $uuid, 365 * 24 * 60, null, null, true, true, false, 'Lax');
    }

    protected function sessionCookie(string $sessionId): Cookie
    {
        // httpOnly=true prevents XSS access; secure=true for HTTPS only
        return cookie('analytics_session_id', $sessionId, $this->sessionInactivityMinutes, null, null, true, true, false, 'Lax');
    }
}
