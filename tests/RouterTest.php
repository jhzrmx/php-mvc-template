<?php

use PHPUnit\Framework\TestCase;

use JHz\Exceptions\HttpException;
use JHz\Http\Request;
use JHz\Http\Response;
use JHz\Http\Route;

class RouterTest extends TestCase {
    private $server;

    protected function setUp(): void {
        $this->server = $_SERVER;
        Route::reset();
    }

    protected function tearDown(): void {
        $_SERVER = $this->server;
    }

    public function testCapturesParametersInOrder() {
        Route::get('/users/:id/posts/:post', 'X@y');
        $r = Route::resolve('GET', '/users/4/posts/9?x=1');
        $this->assertSame(['4', '9'], $r['params']);
    }

    public function testParametersAreUrlDecoded() {
        Route::get('/files/:name', 'X@y');
        $this->assertSame(['a b'], Route::resolve('GET', '/files/a%20b')['params']);
    }

    public function testInlineConstraint() {
        Route::get('/users/:id(\d+)', 'X@y');
        $this->assertNotNull(Route::resolve('GET', '/users/12'));
        $this->assertNull(Route::resolve('GET', '/users/abc'));
    }

    public function testWhereConstraint() {
        Route::get('/tags/:slug', 'X@y')->where('slug', '[a-z-]+');
        $this->assertNotNull(Route::resolve('GET', '/tags/php-tips'));
        $this->assertNull(Route::resolve('GET', '/tags/PHP_123'));
    }

    public function testTrailingSlashAndRoot() {
        Route::get('/', 'X@home');
        Route::get('/about', 'X@about');
        $this->assertSame('X@home', Route::resolve('GET', '/')['route']['callback']);
        $this->assertSame('X@about', Route::resolve('GET', '/about/')['route']['callback']);
        $this->assertNull(Route::resolve('GET', '/nothing'));
    }

    public function testGroupPrefixAndMiddlewareAreScoped() {
        Route::group('/api', function () {
            Route::get('/ping', 'X@ping');
        }, ['auth']);
        Route::get('/outside', 'X@outside');

        $inside = Route::resolve('GET', '/api/ping');
        $this->assertSame(['auth'], $inside['route']['middlewares']);
        $outside = Route::resolve('GET', '/outside');
        $this->assertSame([], $outside['route']['middlewares']);
        $this->assertNull(Route::resolve('GET', '/api/outside'));
    }

    public function testPerRouteMiddlewareIsAppended() {
        Route::group('/api', function () {
            Route::get('/x', 'X@y', ['role:admin'])->middleware('log');
        }, ['auth']);
        $this->assertSame(['auth', 'role:admin', 'log'], Route::resolve('GET', '/api/x')['route']['middlewares']);
    }

    public function testWrongMethodGives405WithAllowedList() {
        Route::post('/items', 'X@store');
        $r = Route::resolve('GET', '/items');
        $this->assertArrayHasKey('allowed', $r);
        $this->assertContains('POST', $r['allowed']);
        $this->assertContains('OPTIONS', $r['allowed']);
    }

    public function testHeadFallsBackToGet() {
        Route::get('/page', 'X@y');
        $this->assertNotNull(Route::resolve('HEAD', '/page')['route'] ?? null);
    }

    public function testAnyMatchesEveryMethod() {
        Route::any('/hook', 'X@y');
        foreach (['GET', 'POST', 'DELETE'] as $method) {
            $this->assertArrayHasKey('route', Route::resolve($method, '/hook'));
        }
    }

    public function testFirstMatchingRouteWins() {
        Route::get('/users/me', 'X@me');
        Route::get('/users/:id', 'X@show');
        $this->assertSame('X@me', Route::resolve('GET', '/users/me')['route']['callback']);
        $this->assertSame('X@show', Route::resolve('GET', '/users/5')['route']['callback']);
    }

    public function testNamedRouteUrls() {
        Route::get('/users/:id(\d+)/posts/:slug', 'X@y')->name('post.show');
        $url = Route::url('post.show', ['id' => 5, 'slug' => 'hello world'], ['page' => 2]);
        $this->assertSame('/users/5/posts/hello%20world?page=2', $url);
    }

    public function testExtraUrlParametersBecomeTheQueryString() {
        Route::get('/search', 'X@y')->name('search');
        $this->assertSame('/search?q=php', Route::url('search', ['q' => 'php']));
    }

    public function testUrlErrors() {
        Route::get('/users/:id', 'X@y')->name('u');
        try {
            Route::url('u');
            $this->fail('Expected a missing-parameter error');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('id', $e->getMessage());
        }
        $this->expectException(InvalidArgumentException::class);
        Route::url('does.not.exist');
    }

    public function testDuplicateRouteNamesAreRejected() {
        Route::get('/a', 'X@y')->name('same');
        $this->expectException(InvalidArgumentException::class);
        Route::get('/b', 'X@y')->name('same');
    }

    public function testRouteListing() {
        Route::get('/a', 'AController@index')->name('a.index');
        Route::any('/b', function () {
        });
        $rows = Route::routes();
        $this->assertSame('GET', $rows[0]['method']);
        $this->assertSame('a.index', $rows[0]['name']);
        $this->assertSame('AController@index', $rows[0]['action']);
        $this->assertSame('ANY', $rows[1]['method']);
        $this->assertSame('Closure', $rows[1]['action']);
    }

    public function testHandlersReceiveRequestAndResponseByTypeHint() {
        // Guards the reflection code that differs between PHP 7.0, 7.1+ and 8.x
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Route::init();

        $handler = function (Response $res, Request $req, $id, $extra = 'dflt') {
        };
        $buildArgs = new ReflectionMethod(Route::class, 'buildArgs');
        $buildArgs->setAccessible(true);
        $args = $buildArgs->invoke(null, new ReflectionFunction($handler), ['42']);

        $this->assertInstanceOf(Response::class, $args[0]);
        $this->assertInstanceOf(Request::class, $args[1]);
        $this->assertSame('42', $args[2]);
        $this->assertSame('dflt', $args[3]);
    }

    public function testApiResourceRegistersFiveRoutesPlusPatch() {
        Route::apiResource('posts', 'PostController');
        $methods = array_map(function ($r) {
            return $r['method'] . ' ' . $r['uri'];
        }, Route::routes());
        $this->assertContains('GET /posts', $methods);
        $this->assertContains('POST /posts', $methods);
        $this->assertContains('GET /posts/:id', $methods);
        $this->assertContains('PUT /posts/:id', $methods);
        $this->assertContains('PATCH /posts/:id', $methods);
        $this->assertContains('DELETE /posts/:id', $methods);
        $this->assertNotContains('GET /posts/create', $methods);
    }

    public function testUnmatchedRequestsThrowANotFoundException() {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/nope';
        Route::get('/home', 'X@y');
        try {
            Route::add404();
            $this->fail('Expected an HttpException');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function testWrongMethodThrowsMethodNotAllowedWithAnAllowHeader() {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/items';
        Route::post('/items', 'X@store');
        try {
            Route::add404();
            $this->fail('Expected an HttpException');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->getStatusCode());
            $this->assertStringContainsString('POST', $e->getHeaders()['Allow']);
        }
    }
}
