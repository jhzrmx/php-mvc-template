<?php

use PHPUnit\Framework\TestCase;

use JHz\Exceptions\ValidationException;
use JHz\Http\Request;

class RequestTest extends TestCase {
    private $server;
    private $get;
    private $post;

    protected function setUp(): void {
        $this->server = $_SERVER;
        $this->get = $_GET;
        $this->post = $_POST;
    }

    protected function tearDown(): void {
        $_SERVER = $this->server;
        $_GET = $this->get;
        $_POST = $this->post;
    }

    public function testPathStripsTheQueryString() {
        $_SERVER['REQUEST_URI'] = '/api/users?page=2';
        $this->assertSame('/api/users', (new Request())->path());
    }

    public function testHeaderLookupIsCaseInsensitive() {
        $_SERVER['HTTP_X_RETURN_TOKEN'] = 'true';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $req = new Request();
        $this->assertSame('true', $req->header('x-return-token'));
        $this->assertSame('application/json', $req->header('Content-Type'));
        $this->assertTrue($req->isJson());
        $this->assertSame('fallback', $req->header('X-Missing', 'fallback'));
    }

    public function testBearerToken() {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc.def.ghi';
        $this->assertSame('abc.def.ghi', (new Request())->bearerToken());
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic zzz';
        $this->assertNull((new Request())->bearerToken());
    }

    public function testWantsJson() {
        $_SERVER['REQUEST_URI'] = '/api/anything';
        $this->assertTrue((new Request())->wantsJson());

        $_SERVER['REQUEST_URI'] = '/dashboard';
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->assertFalse((new Request())->wantsJson());

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->assertTrue((new Request())->wantsJson());
    }

    public function testInputMergesSourcesWithBodyWinning() {
        $_GET = ['page' => '2', 'q' => 'query'];
        $_POST = ['q' => 'form'];
        $req = new Request();
        $req->body = ['q' => 'json', 'extra' => 1];

        $this->assertSame('json', $req->input('q'));
        $this->assertSame('2', $req->input('page'));
        $this->assertSame('dflt', $req->input('missing', 'dflt'));
        $this->assertTrue($req->has('extra'));
        $this->assertSame(['page' => '2'], $req->only(['page']));
    }

    public function testJsonIsEmptyWhenBodyIsNotAnArray() {
        $req = new Request();
        $req->body = 'plain text';
        $this->assertSame([], $req->json());
        $this->assertSame('d', $req->json('x', 'd'));
    }

    public function testJsonRequestBodyMeansTheClientWantsJson() {
        $_SERVER['REQUEST_URI'] = '/submit';
        $_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->assertTrue((new Request())->wantsJson());
    }

    public function testValidateReturnsTheValidatedFields() {
        $req = new Request();
        $req->body = ['name' => 'Ann', 'extra' => 'ignored'];
        $this->assertSame(['name' => 'Ann'], $req->validate(['name' => 'required|string'], [], $req->json()));
    }

    public function testValidateThrowsAValidationException() {
        $req = new Request();
        $req->body = ['name' => ''];
        try {
            $req->validate(['name' => 'required'], [], $req->json());
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('name', $e->errors());
            $this->assertStringContainsString('name', $e->getMessage());
        }
    }
}
