<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VapidKeysCommandTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        // Osobny katalog z plikiem .env - test nie dotyka prawdziwego backend/.env
        $this->dir = storage_path('framework/testing/vapid-' . uniqid());
        File::ensureDirectoryExists($this->dir);
        $this->app->useEnvironmentPath($this->dir);
        config(['webpush.public_key' => '', 'webpush.private_key' => '']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    protected function env(): string
    {
        return (string) file_get_contents($this->dir . '/.env');
    }

    public function test_generates_keys_and_writes_env(): void
    {
        file_put_contents($this->dir . '/.env', "APP_NAME=Test\nVAPID_PUBLIC_KEY=\n");

        $this->artisan('webpush:vapid')
            ->expectsOutput('Wygenerowano klucze VAPID i zapisano je w .env.')
            ->assertSuccessful();

        $env = $this->env();
        $this->assertStringContainsString("APP_NAME=Test\n", $env);
        $this->assertMatchesRegularExpression('/^VAPID_PUBLIC_KEY=[A-Za-z0-9_-]{80,}$/m', $env);
        $this->assertMatchesRegularExpression('/^VAPID_PRIVATE_KEY=[A-Za-z0-9_-]{40,}$/m', $env);
        $this->assertStringContainsString('VAPID_SUBJECT=mailto:', $env);
        $this->assertSame(1, substr_count($env, 'VAPID_PUBLIC_KEY='));
        $this->assertNotEmpty(config('webpush.public_key'));
    }

    public function test_does_not_overwrite_existing_keys_without_force(): void
    {
        file_put_contents($this->dir . '/.env', "VAPID_PUBLIC_KEY=existing-public\nVAPID_PRIVATE_KEY=existing-private\nVAPID_SUBJECT=mailto:me@example.com\n");

        $this->artisan('webpush:vapid')
            ->expectsOutput('Klucze VAPID są już ustawione.')
            ->assertSuccessful();
        $this->assertStringContainsString('VAPID_PUBLIC_KEY=existing-public', $this->env());

        $this->artisan('webpush:vapid --force')->assertSuccessful();
        $env = $this->env();
        $this->assertStringNotContainsString('existing-public', $env);
        $this->assertStringContainsString('VAPID_SUBJECT=mailto:me@example.com', $env);
    }

    public function test_show_option_does_not_write(): void
    {
        file_put_contents($this->dir . '/.env', "APP_NAME=Test\n");

        $this->artisan('webpush:vapid --show')->assertSuccessful();

        $this->assertSame("APP_NAME=Test\n", $this->env());
    }
}
