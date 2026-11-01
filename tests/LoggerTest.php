<?php

use PHPUnit\Framework\TestCase;

use JHz\Support\Log;

class LoggerTest extends TestCase {
    private $dir;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . '/log_' . uniqid();
        Log::setDirectory($this->dir);
        $_ENV['LOG_LEVEL'] = 'info';
    }

    protected function tearDown(): void {
        unset($_ENV['LOG_LEVEL']);
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    private function contents() {
        $files = glob($this->dir . '/app-*.log') ?: [];
        return $files ? file_get_contents($files[0]) : '';
    }

    public function testWritesOneLinePerEntryWithContext() {
        Log::info("hello\nworld", ['id' => 5]);
        $lines = array_filter(explode("\n", $this->contents()));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('info: hello world', $lines[0]);
        $this->assertStringContainsString('{"id":5}', $lines[0]);
    }

    public function testRespectsTheMinimumLevel() {
        Log::debug('too quiet');
        $this->assertSame('', $this->contents());
        Log::warning('loud enough');
        $this->assertStringContainsString('warning: loud enough', $this->contents());
    }

    public function testExceptionsAreLoggedWithTheirClass() {
        Log::exception(new RuntimeException('boom'));
        $this->assertStringContainsString('boom', $this->contents());
        $this->assertStringContainsString('RuntimeException', $this->contents());
    }
}
