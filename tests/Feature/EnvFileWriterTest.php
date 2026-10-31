<?php

namespace Tests\Feature;

use App\Services\Install\EnvFileWriter;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

/**
 * The installer writes an operator-supplied database password and an application
 * key into `.env`. Dotenv is line-oriented, so a value containing a newline can
 * terminate its own key/value pair and inject arbitrary settings — which is
 * remote code execution by another route, because the app reads that file on every
 * request and `APP_KEY` is among the injectable keys.
 *
 * Escaping is not available here: dotenv has no escape syntax that survives a
 * round trip through `env()`, and a password is allowed to contain almost
 * anything. So values are rejected instead, which is what these tests pin down.
 */
class EnvFileWriterTest extends TestCase
{
    protected function writer(): EnvFileWriter
    {
        return new EnvFileWriter($this->app->make(Filesystem::class));
    }

    /**
     * A complete, valid settings array. Individual tests override one key.
     *
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
            'db_password' => 'a-normal-password',
        ], $overrides);
    }

    public function test_a_newline_in_the_database_password_is_rejected(): void
    {
        // The whole attack: terminate DB_PASSWORD and start a new setting.
        $payload = "secret\nAPP_DEBUG=true";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/line breaks/');

        $this->writer()->render($this->settings(['db_password' => $payload]));
    }

    public function test_a_newline_in_the_database_password_cannot_inject_a_second_app_key(): void
    {
        $payload = "x\nAPP_KEY=base64:attacker";

        try {
            $this->writer()->render($this->settings(['db_password' => $payload]));
            $this->fail('Expected a newline-bearing password to be rejected.');
        } catch (\InvalidArgumentException) {
            // Expected. The rendered file must never be reached.
        }

        $this->assertTrue(true);
    }

    public function test_a_carriage_return_is_also_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->writer()->render($this->settings(['db_password' => "secret\rAPP_DEBUG=true"]));
    }

    public function test_a_null_byte_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->writer()->render($this->settings(['db_password' => "secret\0APP_DEBUG=true"]));
    }

    public function test_a_double_quote_in_the_password_is_rejected(): void
    {
        // A quote would end the quoted value early and let the remainder be
        // parsed as a new token.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/double quote/');

        $this->writer()->render($this->settings(['db_password' => 'abc"def']));
    }

    public function test_a_value_starting_with_a_comment_marker_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/comment marker/');

        $this->writer()->render($this->settings(['app_name' => ' #not-a-name']));
    }

    public function test_a_newline_in_the_application_name_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->writer()->render($this->settings(['app_name' => "Store\nDB_PASSWORD=x"]));
    }

    public function test_a_newline_in_any_value_is_rejected_regardless_of_field(): void
    {
        // The guard runs over every value, so a future validation change cannot
        // quietly reopen the hole for one specific field.
        foreach (['app_name', 'app_url', 'db_host', 'db_database', 'db_username', 'db_password'] as $field) {
            try {
                $this->writer()->render($this->settings([$field => "ok\nINJECTED=1"]));
                $this->fail("Expected {$field} to reject a newline.");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_ordinary_password_with_punctuation_is_allowed(): void
    {
        // The guard must not be so strict that a legitimate password is refused.
        $password = "p@ss w0rd!#%&*()-_=+[]{};',.<>/?";

        $rendered = $this->writer()->render($this->settings(['db_password' => $password]));

        $this->assertStringContainsString('DB_PASSWORD="'.$password.'"', $rendered);
    }

    public function test_a_password_with_an_equals_sign_is_allowed_and_stays_on_one_line(): void
    {
        $rendered = $this->writer()->render($this->settings(['db_password' => 'a=b=c']));

        $this->assertStringContainsString('DB_PASSWORD="a=b=c"', $rendered);
    }

    public function test_every_written_line_is_a_single_setting(): void
    {
        // Structural check: no rendered line may contain anything other than
        // KEY=value, so nothing has smuggled extra settings in.
        $rendered = $this->writer()->render($this->settings(['db_password' => 'str0ng!pass']));

        foreach (explode(PHP_EOL, trim($rendered)) as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^[A-Z][A-Z0-9_]*=/',
                $line,
                "Unexpected rendered line: {$line}"
            );
        }
    }

    public function test_the_rendered_file_has_debug_off(): void
    {
        // A generated .env with APP_DEBUG=true would leak the whole file into any
        // error page.
        $rendered = $this->writer()->render($this->settings());

        $this->assertStringContainsString('APP_DEBUG=false', $rendered);
        $this->assertStringNotContainsString('APP_DEBUG=true', $rendered);
    }

    public function test_the_rendered_file_has_no_nested_mail_from_interpolation(): void
    {
        // "${APP_NAME@${APP_URL}}" is not supported by dotenv; it resolves to a
        // literal and every send fails with an RFC 2822 addr-spec error.
        $rendered = $this->writer()->render($this->settings());

        $this->assertStringNotContainsString('${APP_NAME@', $rendered);
        $this->assertMatchesRegularExpression('/^MAIL_FROM_ADDRESS="[^"]+"$/m', $rendered);
    }

    public function test_the_admin_password_is_never_written_to_the_env_file(): void
    {
        // The administrator is created directly by InstallerService. Persisting
        // the password here would leave a real credential in a file that any
        // local user, backup or stray git add can read.
        $rendered = $this->writer()->render($this->settings());

        $this->assertMatchesRegularExpression('/^ADMIN_PASSWORD=$/m', $rendered);
    }

    public function test_the_url_is_stored_without_a_trailing_slash(): void
    {
        $rendered = $this->writer()->render($this->settings(['app_url' => 'https://shop.example.com/']));

        $this->assertStringContainsString('APP_URL="https://shop.example.com"', $rendered);
        $this->assertStringNotContainsString('example.com/"', $rendered);
    }

    public function test_the_port_is_written_as_a_bare_integer(): void
    {
        $rendered = $this->writer()->render($this->settings(['db_port' => '3307']));

        $this->assertStringContainsString('DB_PORT=3307', $rendered);
    }

    public function test_the_database_name_pattern_rejects_sql_metacharacters(): void
    {
        // The pattern is a constant on the writer; assert it there rather than
        // reading the rules array, whose keys are rule names rather than messages.
        $rules = $this->writer()->rules();

        $this->assertContains('regex:'.EnvFileWriter::DATABASE_PATTERN, $rules['db_database']);

        // preg_match returns 1 when the pattern matches. A valid name matches;
        // anything carrying SQL metacharacters must not.
        $pattern = EnvFileWriter::DATABASE_PATTERN;

        $this->assertSame(1, preg_match($pattern, 'good_name'));
        $this->assertSame(0, preg_match($pattern, 'good`name'));
        $this->assertSame(0, preg_match($pattern, 'good name'));
        $this->assertSame(0, preg_match($pattern, 'good;DROP'));
    }

    public function test_the_host_pattern_rejects_ipv6(): void
    {
        // Bracket syntax collides with the way the value is written, so IPv6 is
        // excluded rather than half-supported.
        $this->assertSame(1, preg_match(EnvFileWriter::HOST_PATTERN, 'localhost'));
        $this->assertSame(1, preg_match(EnvFileWriter::HOST_PATTERN, '127.0.0.1'));
        $this->assertSame(0, preg_match(EnvFileWriter::HOST_PATTERN, '[::1]'));
        $this->assertSame(0, preg_match(EnvFileWriter::HOST_PATTERN, '::1'));
    }
}
