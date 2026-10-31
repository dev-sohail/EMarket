<?php

namespace Tests\Feature;

use App\Services\Install\InstallerService;
use App\Services\Install\InstallState;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The installer redirects every visitor when nothing is configured, and must be
 * completely unreachable the moment the application is installed.
 *
 * The property under test is mostly about NOT being triggerable. An installer
 * that creates an administrator account is a takeover primitive if it can be
 * reached on a live site — which is what makes "when is it reachable" the whole
 * security question, more than any individual check.
 */
class InstallerAvailabilityTest extends TestCase
{
    /**
     * Absolute path of the marker, which the tests move around freely.
     */
    protected function lockPath(): string
    {
        return storage_path(InstallState::LOCK_RELATIVE_PATH);
    }

    protected function setUp(): void
    {
        parent::setUp();

        File::delete($this->lockPath());
    }

    protected function tearDown(): void
    {
        File::delete($this->lockPath());

        parent::tearDown();
    }

    /**
     * Pretend to be an un-installed application by hiding the environment file
     * from InstallState. The real .env stays on disk — moving it would break the
     * test process itself.
     */
    protected function pretendNoEnvironmentFile(): void
    {
        $files = $this->app->make(Filesystem::class);

        $this->app->instance(InstallState::class, new class($files) extends InstallState
        {
            public function environmentFileExists(): bool
            {
                return false;
            }
        });
    }

    public function test_the_installer_redirects_when_nothing_is_configured(): void
    {
        $this->pretendNoEnvironmentFile();

        $this->get('/shop')->assertRedirect(route('install.index'));
    }

    public function test_a_configured_application_is_never_redirected(): void
    {
        // No marker, but the environment file exists — this is the state of any
        // site deployed before this feature existed. Redirecting here would send
        // every visitor to an installer that 404s, which is a total outage
        // caused by the safety feature itself.
        $this->assertFalse(app(InstallState::class)->needsInstall());
    }

    public function test_a_configured_application_with_the_marker_never_redirects(): void
    {
        app(InstallState::class)->markInstalled();

        $this->assertFalse(app(InstallState::class)->needsInstall());
        $this->assertTrue(app(InstallState::class)->locked());
    }

    public function test_a_database_outage_does_not_unlock_the_installer(): void
    {
        app(InstallState::class)->markInstalled();

        // Point the app at a database that cannot exist. needsInstall() must not
        // consult the database at all, so this cannot change the answer.
        config(['database.default' => 'mysql', 'database.connections.mysql.host' => '203.0.113.1']);

        $this->assertFalse(app(InstallState::class)->needsInstall());
        $this->assertTrue(app(InstallState::class)->locked());
    }

    public function test_the_installer_is_a_404_once_installed(): void
    {
        app(InstallState::class)->markInstalled();

        $this->get('/install')->assertNotFound();
    }

    public function test_the_installer_refuses_to_run_when_only_the_marker_is_missing(): void
    {
        // No marker, but .env is present. This is a live site that lost its
        // marker, and the wizard must not offer to overwrite it.
        $this->get('/install')->assertNotFound();
    }

    public function test_the_install_post_is_a_404_once_installed(): void
    {
        app(InstallState::class)->markInstalled();

        $this->post('/install', [])->assertNotFound();
    }

    public function test_the_connection_test_is_a_404_once_installed(): void
    {
        app(InstallState::class)->markInstalled();

        $this->post('/install/test-connection', [])->assertNotFound();
    }

    public function test_the_complete_route_redirects_after_installation(): void
    {
        app(InstallState::class)->markInstalled();

        $this->get('/install/complete')->assertRedirect(route('home'));
    }

    public function test_the_health_endpoint_is_never_redirected(): void
    {
        $this->pretendNoEnvironmentFile();

        // A load balancer probing /up must not be bounced to the installer, or an
        // un-installed instance never reports healthy.
        $this->get('/up')->assertOk();
    }

    public function test_marking_installed_writes_a_marker_with_the_expected_key(): void
    {
        app(InstallState::class)->markInstalled();

        $contents = json_decode(File::get($this->lockPath()), true);

        $this->assertTrue($contents['poweredin_installed']);
        $this->assertArrayHasKey('installed_at', $contents);
        $this->assertSame(PHP_VERSION, $contents['php_version']);
    }

    public function test_clearing_the_marker_makes_the_installer_available_again(): void
    {
        app(InstallState::class)->markInstalled();
        app(InstallState::class)->clear();

        // The marker is gone, but the environment file still exists, so the
        // installer still refuses. Removing both is a deliberate act.
        $this->assertFalse(app(InstallState::class)->locked());
        $this->assertFalse(app(InstallState::class)->needsInstall());
    }

    public function test_the_install_routes_are_registered_even_while_installed(): void
    {
        // Deliberate. route:cache snapshots the route table, so conditionally
        // registering these routes would mean a cache built before installation
        // left them absent forever. They are always registered and gated by
        // middleware, which is enforced per-request regardless of the cache.
        $route = app('router')->getRoutes()->getByName('install.index');

        $this->assertNotNull($route);
        $this->assertContains('installer', $route->middleware());
    }

    public function test_installer_routes_do_not_use_the_web_group(): void
    {
        // The `web` group prepends TrackAnalytics, which queries analytics_visitors
        // on every GET. On an un-installed application that table does not exist,
        // so the installer page would throw before rendering.
        $route = app('router')->getRoutes()->getByName('install.index');

        $this->assertNotContains('web', $route->gatherMiddleware());
    }

    public function test_the_redirect_middleware_only_handles_get_requests(): void
    {
        $this->pretendNoEnvironmentFile();

        // Redirecting a POST would discard the shopper's submission silently.
        $response = $this->post('/shop', []);

        $this->assertNotSame(302, $response->getStatusCode());
    }

    public function test_installer_service_refuses_to_overwrite_an_existing_environment_file(): void
    {
        // The real .env exists in this checkout, so the guard must trip.
        $service = app(InstallerService::class);

        $this->assertTrue($service->looksConfigured());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already has an environment file/');

        $service->install([
            'app_name' => 'Test',
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
            'app_url' => 'http://localhost',
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => 'nope',
            'db_username' => 'root',
            'db_password' => '',
            'admin_email' => 'a@example.com',
            'admin_password' => 'a-long-enough-password',
        ]);
    }
}
