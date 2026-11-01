<?php

use PHPUnit\Framework\TestCase;

/**
 * View rendering: a view or layout that fails must never leave partial output or open buffers behind.
 */
class ResponseTest extends TestCase {
    protected function setUp(): void {
        Response::$viewsDir = 'tests/fixtures/views';
    }

    protected function tearDown(): void {
        Response::$viewsDir = 'views';
    }

    /**
     * Render a view that is expected to fail. Asserts nothing leaked and no buffer was left open.
     */
    private function renderFailing($view) {
        $before = ob_get_level();
        ob_start();
        $thrown = null;
        try {
            (new Response())->render($view);
        } catch (Throwable $e) {
            $thrown = $e;
        }
        $leaked = ob_get_clean();

        $this->assertSame($before, ob_get_level(), 'output buffers must be restored');
        $this->assertSame('', $leaked, 'partial output must not leak');
        $this->assertNotNull($thrown, 'the view was expected to fail');
        return $thrown;
    }

    public function testRenderReturnsTheHtml() {
        $html = (new Response())->render('ok', ['name' => 'Ann']);
        $this->assertSame('<p>Ann</p>', $html);
    }

    public function testViewEscapingIsUpToTheViewButEIsAvailable() {
        $html = (new Response())->render('ok', ['name' => '<b>x</b>']);
        $this->assertSame('<p>&lt;b&gt;x&lt;/b&gt;</p>', $html);
    }

    public function testLayoutWrapsThePage() {
        $html = (new Response())->render('layouted', ['name' => 'Ann']);
        $this->assertSame('[T|body-Ann]', $html);
    }

    public function testPassedVariablesReachTheView() {
        $html = (new Response())->pass(['name' => 'Bo'])->render('ok');
        $this->assertSame('<p>Bo</p>', $html);
    }

    public function testExceptionInAViewDiscardsPartialOutput() {
        $e = $this->renderFailing('throws');
        $this->assertInstanceOf(RuntimeException::class, $e);
        $this->assertSame('view exploded', $e->getMessage());
    }

    public function testExceptionInALayoutDiscardsPartialOutput() {
        $e = $this->renderFailing('layout_throws');
        $this->assertSame('layout exploded', $e->getMessage());
    }

    public function testAbortInsideAViewIsAnHttpException() {
        $e = $this->renderFailing('aborts');
        $this->assertInstanceOf(HttpException::class, $e);
        $this->assertSame(404, $e->getStatusCode());
        $this->assertSame('Post not found', $e->getMessage());
    }

    public function testPhpWarningInAViewBecomesAnException() {
        set_error_handler([ErrorHandler::class, 'handleError']);
        try {
            $e = $this->renderFailing('warns');
        } finally {
            restore_error_handler();
        }
        $this->assertInstanceOf(ErrorException::class, $e);
    }

    public function testABufferTheViewForgotToCloseIsClosed() {
        $before = ob_get_level();
        $html = (new Response())->render('leaky');
        $this->assertSame('leaky', $html);
        $this->assertSame($before, ob_get_level());
    }

    public function testCaptureCleansUpWhenTheFileThrows() {
        $before = ob_get_level();
        ob_start();
        try {
            Response::capture(BASE_PATH . '/tests/fixtures/views/throws.php');
            $this->fail('Expected an exception');
        } catch (RuntimeException $e) {
            // expected
        }
        $leaked = ob_get_clean();
        $this->assertSame('', $leaked);
        $this->assertSame($before, ob_get_level());
    }

    public function testInvalidViewNamesAreRejected() {
        foreach (['../secret', '/etc/passwd', 'a//b', 'with space', 'x.php', ''] as $name) {
            try {
                (new Response())->render($name);
                $this->fail("Expected '$name' to be rejected");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid view name', $e->getMessage());
            }
        }
    }

    public function testMissingViewThrows() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('View not found');
        (new Response())->render('does-not-exist');
    }

    public function testInvalidHeaderNamesAreRejected() {
        $this->expectException(InvalidArgumentException::class);
        (new Response())->header("Bad Name\r\nX-Evil", 'x');
    }
}
