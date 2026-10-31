<?php

namespace Tests\Feature;

use App\Services\Install\EnvFileWriter;
use App\Services\Install\InstallerService;
use App\Services\Install\InstallState;
use App\Services\Install\TemporaryApplicationKey;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

/**
 * The installer's failure reporting, and the settings guard that sits in front of
 * every credential.
 *
 * The reporting rules are what keep a write-capable, unauthenticated endpoint from
 * becoming an information leak. A PDO exception names the host, the username and
 * sometimes the rejected password; showing it to the person who submitted the form
 * hands them a free oracle for enumerating credentials.
 */
class InstallerServiceTest extends TestCase
{
    protected function service(): InstallerService
    {
        return new InstallerService(
            new InstallState(new Filesystem),
            new EnvFileWriter(new Filesystem),
            new Filesystem,
            new TemporaryApplicationKey(new Filesystem),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function settings(array $overrides = []): array
    {
        return array_merge([
            'app_name' => 'PoweredIn',
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
            'app_url' => 'http://localhost:8000',
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => 'test_database',
            'db_username' => 'root',
            'db_password' => '',
        ], $overrides);
    }

    public function test_a_connection_failure_does_not_disclose_the_driver_message(): void
    {
        $result = $this->service()->testConnection($this->settings([
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => 'definitely_not_a_database_xyz',
            'db_username' => 'nobody_at_all',
            'db_password' => 'wrong',
        ]));

        $this->assertFalse($result['ok']);

        // None of the driver's specifics may appear.
        $this->assertStringNotContainsString('SQLSTATE', $result['message']);
        $this->assertStringNotContainsString('Access denied', $result['message']);
        $this->assertStringNotContainsString('nobody_at_all', $result['message']);
        $this->assertStringNotContainsString('wrong', $result['message']);
        $this->assertStringNotContainsString('1045', $result['message']);
    }

    public function test_a_connection_failure_does_not_disclose_the_host(): void
    {
        $result = $this->service()->testConnection($this->settings([
            'db_host' => 'db.internal.invalid',
            'db_username' => 'root',
        ]));

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('db.internal.invalid', $result['message']);
    }

    public function test_an_unreachable_server_reports_cleanly(): void
    {
        // Port 1 is reserved and nothing listens on it.
        $result = $this->service()->testConnection($this->settings([
            'db_host' => '127.0.0.1',
            'db_port' => '1',
        ]));

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('Connection refused', $result['message']);
        $this->assertStringNotContainsString('SQLSTATE', $result['message']);
    }

    public function test_a_sql_metacharacter_in_the_database_name_is_refused_before_any_query(): void
    {
        // The form's pattern rule catches this, but the service must not depend
        // on the form having run.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/disallowed character/');

        $this->service()->testConnection($this->settings(['db_database' => 'x`;DROP DATABASE y;--']));
    }

    public function test_a_sql_metacharacter_in_the_username_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->testConnection($this->settings(['db_username' => "root' OR '1'='1"]));
    }

    public function test_an_out_of_range_port_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/between 1 and 65535/');

        $this->service()->testConnection($this->settings(['db_port' => '99999']));
    }

    public function test_a_non_numeric_port_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->testConnection($this->settings(['db_port' => 'mysql']));
    }

    public function test_a_missing_setting_is_refused(): void
    {
        $settings = $this->settings();
        unset($settings['db_database']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/db_database/');

        $this->service()->testConnection($settings);
    }

    public function test_a_blank_database_password_is_accepted_as_a_credential(): void
    {
        // ConvertEmptyStringsToNull turns an empty root password into null. A
        // blanket "must be scalar" check rejected it, so every blank-password
        // attempt reported a connection failure even when the credentials were
        // correct. Null is a legitimate credential here.
        $settings = $this->settings(['db_username' => 'root', 'db_password' => null]);

        // Reaching the connection attempt rather than throwing a guard error is
        // the assertion; the result depends on whether MySQL is reachable.
        $result = $this->service()->testConnection($settings);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ok', $result);
    }
}
