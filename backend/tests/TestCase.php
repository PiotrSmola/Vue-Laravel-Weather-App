<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Zabezpieczenie: RefreshDatabase czyści bazę - testy mogą działać tylko na sqlite w pamięci
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException("Testy muszą używać sqlite :memory: (jest: {$connection} / {$database}). Sprawdź phpunit.xml.");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Testy nigdy nie łączą się z prawdziwym API - każde zapytanie musi być zafake'owane
        Http::preventStrayRequests();
    }
}
