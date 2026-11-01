<?php

use PHPUnit\Framework\TestCase;

use JHz\Exceptions\ErrorHandler;
use JHz\Exceptions\HttpException;
use JHz\Exceptions\ValidationException;
use JHz\Http\Request;
use JHz\Http\Response;
use JHz\Http\Route;
use JHz\Support\Log;

class ErrorHandlerTest extends TestCase {
    private $server;

    protected function setUp(): void {
        $this->server = $_SERVER;
        Route::reset();
        $this->clearEnv();
    }

    protected function tearDown(): void {
        $_SERVER = $this->server;
        Response::$viewsDir = 'views';
        Log::setDirectory(null);
        $this->clearEnv();
    }

    private function clearEnv() {
        unset($_ENV['APP_DEBUG'], $_ENV['APP_ENV'], $_ENV['ERRORS_AS_EXCEPTIONS']);
    }

    private function request($uri, $method = 'GET') {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_X_REQUESTED_WITH']);
        return new Request();
    }

    public function testHttpExceptionAsJson() {
        $r = ErrorHandler::response(new HttpException(404, 'Post not found'), $this->request('/api/posts/1'));
        $this->assertSame(404, $r['status']);
        $this->assertSame('application/json', $r['contentType']);
        $data = json_decode($r['body'], true);
        $this->assertSame('Not Found', $data['error']);
        $this->assertSame('Post not found', $data['message']);
        $this->assertArrayNotHasKey('debug', $data);
    }

    public function testExceptionHeadersAreForwarded() {
        $e = new HttpException(429, 'Slow down', ['Retry-After' => '30']);
        $r = ErrorHandler::response($e, $this->request('/api/login'));
        $this->assertSame('30', $r['headers']['Retry-After']);
    }

    public function testValidationErrorsAreIncluded() {
        $e = new ValidationException(['name' => ['The name field is required.']], 'The name field is required.');
        $r = ErrorHandler::response($e, $this->request('/api/things'));
        $data = json_decode($r['body'], true);
        $this->assertSame(422, $r['status']);
        $this->assertSame('Validation failed', $data['error']);
        $this->assertSame('The name field is required.', $data['errors']['name'][0]);
    }

    public function testUnexpectedErrorsAreHiddenInProduction() {
        $_ENV['APP_ENV'] = 'production';
        $r = ErrorHandler::response(new RuntimeException('secret db password'), $this->request('/api/x'));
        $data = json_decode($r['body'], true);
        $this->assertSame(500, $r['status']);
        $this->assertSame('An unexpected error occurred', $data['message']);
        $this->assertArrayNotHasKey('debug', $data);
        $this->assertStringNotContainsString('secret', $r['body']);
    }

    public function testDebugModeShowsDetailsWithRelativePaths() {
        $_ENV['APP_DEBUG'] = 'true';
        $r = ErrorHandler::response(new RuntimeException('secret db password'), $this->request('/api/x'));
        $data = json_decode($r['body'], true);
        $this->assertStringContainsString('secret db password', $data['message']);
        $this->assertSame('RuntimeException', $data['debug']['exception']);
        $this->assertIsArray($data['debug']['trace']);
        $this->assertStringContainsString('ErrorHandlerTest.php', $data['debug']['file']);
        $this->assertStringNotContainsString(BASE_PATH, $data['debug']['file']);
    }

    public function testClientErrorsNeverShowDebugDetails() {
        $_ENV['APP_DEBUG'] = 'true';
        $r = ErrorHandler::response(new HttpException(403, 'Nope'), $this->request('/api/x'));
        $this->assertArrayNotHasKey('debug', json_decode($r['body'], true));
    }

    public function testHtmlErrorPageEscapesTheMessage() {
        $r = ErrorHandler::response(new HttpException(400, '<script>alert(1)</script>'), $this->request('/page'));
        $this->assertSame(400, $r['status']);
        $this->assertSame('text/html; charset=utf-8', $r['contentType']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $r['body']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $r['body']);
        $this->assertStringContainsString('400', $r['body']);
    }

    public function testHtmlErrorPageListsValidationErrors() {
        $e = new ValidationException(['email' => ['The email field must be a valid email address.']], 'Invalid');
        $r = ErrorHandler::response($e, $this->request('/form'));
        $this->assertStringContainsString('The email field must be a valid email address.', $r['body']);
    }

    public function testBuiltInPageIsUsedWhenThereIsNoErrorView() {
        Response::$viewsDir = 'tests/fixtures/views'; // has no errors/ folder
        $r = ErrorHandler::response(new HttpException(404, 'Gone fishing'), $this->request('/page'));
        $this->assertStringContainsString('404 Not Found', $r['body']);
        $this->assertStringContainsString('Gone fishing', $r['body']);
    }

    public function testBrokenErrorViewFallsBackToTheBuiltInPage() {
        $logDir = sys_get_temp_dir() . '/errlog_' . uniqid();
        Log::setDirectory($logDir);
        Response::$viewsDir = 'tests/fixtures/broken_error_views';
        try {
            $r = ErrorHandler::response(new HttpException(500, 'Oops'), $this->request('/page'));
            $this->assertSame(500, $r['status']);
            $this->assertStringContainsString('500 Internal Server Error', $r['body']);
            $this->assertStringContainsString('Oops', $r['body']);
        } finally {
            foreach (glob($logDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($logDir);
        }
    }

    public function testAFailingViewEndsUpAsAnErrorPageNotABrokenPage() {
        // The whole story: a view throws -> buffers are clean -> the handler builds a proper response
        Response::$viewsDir = 'tests/fixtures/views';
        $before = ob_get_level();
        try {
            (new Response())->render('throws');
            $this->fail('Expected the view to throw');
        } catch (Throwable $e) {
            $this->assertSame($before, ob_get_level());
            Response::$viewsDir = 'views';
            $_ENV['APP_ENV'] = 'production';
            $r = ErrorHandler::response($e, $this->request('/dashboard'));
        }
        $this->assertSame(500, $r['status']);
        $this->assertStringNotContainsString('partial output', $r['body']);
        $this->assertStringNotContainsString('view exploded', $r['body']);
        $this->assertStringContainsString('An unexpected error occurred', $r['body']);
    }

    public function testAnAbortInsideAViewBecomesA404Page() {
        Response::$viewsDir = 'tests/fixtures/views';
        try {
            (new Response())->render('aborts');
            $this->fail('Expected abort()');
        } catch (HttpException $e) {
            Response::$viewsDir = 'views';
            $r = ErrorHandler::response($e, $this->request('/posts/9'));
        }
        $this->assertSame(404, $r['status']);
        $this->assertStringContainsString('Post not found', $r['body']);
    }

    public function testHeadRequestsGetNoBody() {
        $r = ErrorHandler::response(new HttpException(404), $this->request('/api/x', 'HEAD'));
        $this->assertSame(404, $r['status']);
        $this->assertSame('', $r['body']);
    }

    public function testJsonClientsOnNonApiPathsGetJson() {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_URI'] = '/submit';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $r = ErrorHandler::response(new HttpException(400, 'Bad'), new Request());
        $this->assertSame('application/json', $r['contentType']);
    }

    public function testDebugFlag() {
        $this->assertFalse(ErrorHandler::debug(), 'no env, not on this machine');

        $_ENV['APP_ENV'] = 'production';
        $this->assertFalse(ErrorHandler::debug());
        $_ENV['APP_ENV'] = 'staging';
        $this->assertFalse(ErrorHandler::debug());
        $_ENV['APP_ENV'] = 'local';
        $this->assertTrue(ErrorHandler::debug());

        $_ENV['APP_DEBUG'] = 'false';
        $this->assertFalse(ErrorHandler::debug(), 'APP_DEBUG overrides APP_ENV');
        $_ENV['APP_DEBUG'] = '1';
        $_ENV['APP_ENV'] = 'production';
        $this->assertTrue(ErrorHandler::debug());
    }

    public function testNoEnvironmentOnlyShowsDetailsToTheSameMachine() {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $this->assertFalse(ErrorHandler::debug());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertTrue(ErrorHandler::debug());
    }

    public function testWarningsBecomeErrorExceptions() {
        try {
            ErrorHandler::handleError(E_WARNING, 'boom', 'file.php', 12);
            $this->fail('Expected an ErrorException');
        } catch (ErrorException $e) {
            $this->assertSame('boom', $e->getMessage());
            $this->assertSame(E_WARNING, $e->getSeverity());
            $this->assertSame(12, $e->getLine());
        }
    }

    public function testDeprecationsAreSwallowed() {
        $this->assertTrue(ErrorHandler::handleError(E_DEPRECATED, 'old api', 'file.php', 1));
        $this->assertTrue(ErrorHandler::handleError(E_USER_DEPRECATED, 'old api', 'file.php', 1));
    }

    public function testSilencedErrorsAreIgnored() {
        $old = error_reporting(0);
        try {
            $this->assertFalse(ErrorHandler::handleError(E_WARNING, 'silenced', 'file.php', 1));
        } finally {
            error_reporting($old);
        }
    }

    public function testErrorsAsExceptionsCanBeSwitchedOff() {
        $_ENV['ERRORS_AS_EXCEPTIONS'] = 'false';
        $this->assertFalse(ErrorHandler::handleError(E_WARNING, 'ignored', 'file.php', 1));
    }
}
