<?php

namespace WpOop\HttpClient\Test;

use PHPUnit\Framework\TestCase;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * A base class for tests that interact with WordPress.
 */
class WpTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        tearDown();
    }
}
