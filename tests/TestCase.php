<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Refuse to run against anything that is not a dedicated test database.
     *
     * The whole suite runs under RefreshDatabase, which migrates fresh — it
     * drops every table before the first test. If `.env.testing` is missing,
     * APP_ENV=testing silently falls back to `.env`, and the suite empties the
     * development database instead. That has happened; this is the guard.
     *
     * The name has to opt in rather than merely differ from the dev database,
     * because the fallback case has no dev name to compare against.
     */
    protected function setUp(): void
    {
        $this->guardTestDatabase();

        parent::setUp();
    }

    private function guardTestDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        // In-memory SQLite is disposable by definition.
        if ($database === ':memory:' || $database === '') {
            return;
        }

        if (str_contains(strtolower(basename($database)), 'test')) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run tests against the '{$database}' database: the name does not "
            . "identify it as a test database, and the suite would migrate fresh over it. "
            . "Create a .env.testing pointing DB_DATABASE at a disposable database "
            . "(the project uses 'nextbook_test'). See TESTING_GUIDE.md."
        );
    }
}
