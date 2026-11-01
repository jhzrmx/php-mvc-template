<?php

use PHPUnit\Framework\TestCase;

use JHz\Security\RateLimiter;

class RateLimiterTest extends TestCase {
    private $dir;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . '/rl_' . uniqid();
        RateLimiter::setDirectory($this->dir);
    }

    protected function tearDown(): void {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testBlocksAfterTheLimit() {
        $this->assertFalse(RateLimiter::tooManyAttempts('k', 3));
        $this->assertTrue(RateLimiter::hit('k', 3, 60));
        $this->assertTrue(RateLimiter::hit('k', 3, 60));
        $this->assertTrue(RateLimiter::hit('k', 3, 60));
        $this->assertTrue(RateLimiter::tooManyAttempts('k', 3));
        $this->assertFalse(RateLimiter::hit('k', 3, 60));
        $this->assertGreaterThan(0, RateLimiter::retryAfter('k'));
    }

    public function testClearResetsTheCounter() {
        RateLimiter::hit('k', 1, 60);
        $this->assertTrue(RateLimiter::tooManyAttempts('k', 1));
        RateLimiter::clear('k');
        $this->assertFalse(RateLimiter::tooManyAttempts('k', 1));
    }

    public function testKeysAreIndependent() {
        RateLimiter::hit('a', 1, 60);
        $this->assertTrue(RateLimiter::tooManyAttempts('a', 1));
        $this->assertFalse(RateLimiter::tooManyAttempts('b', 1));
    }

    public function testReadOnlyChecksDoNotCreateFiles() {
        RateLimiter::tooManyAttempts('never-hit', 5);
        $this->assertSame([], glob($this->dir . '/*') ?: []);
    }
}
