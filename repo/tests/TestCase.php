<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cache uses the in-memory 'array' driver in tests, which persists across
        // cases inside a single PHPUnit process. Middleware like RequestFrequencyGuard
        // keys off Cache, so stale counters from earlier tests can push later tests
        // over the limit and produce spurious 429s. Flush per test for isolation.
        Cache::flush();
    }
}
