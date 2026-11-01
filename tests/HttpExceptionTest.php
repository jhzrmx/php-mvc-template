<?php

use PHPUnit\Framework\TestCase;

class HttpExceptionTest extends TestCase {
    public function testStatusTitleAndHeaders() {
        $e = new HttpException(429, 'Slow down', ['Retry-After' => 30]);
        $this->assertSame(429, $e->getStatusCode());
        $this->assertSame('Too Many Requests', $e->getTitle());
        $this->assertSame('Slow down', $e->getMessage());
        $this->assertSame(['Retry-After' => 30], $e->getHeaders());
        $this->assertSame([], $e->getData());
    }

    public function testInvalidStatusBecomes500() {
        $this->assertSame(500, (new HttpException(200))->getStatusCode());
        $this->assertSame(500, (new HttpException(99))->getStatusCode());
        $this->assertSame(500, (new HttpException(700))->getStatusCode());
    }

    public function testUnknownStatusesGetAGenericTitle() {
        $this->assertSame('Error', HttpException::titleFor(418));
        $this->assertSame('Server Error', HttpException::titleFor(599));
    }

    public function testIsARuntimeException() {
        $this->assertInstanceOf(RuntimeException::class, new HttpException(404));
    }

    public function testValidationException() {
        $e = new ValidationException(['name' => ['The name field is required.']]);
        $this->assertSame(422, $e->getStatusCode());
        $this->assertSame('Validation failed', $e->getTitle());
        $this->assertSame('The given data was invalid.', $e->getMessage());
        $this->assertSame(['name' => ['The name field is required.']], $e->errors());
        $this->assertSame(['errors' => $e->errors()], $e->getData());
    }

    public function testAbortHelpers() {
        try {
            abort(403, 'Admins only', ['X-Test' => '1']);
            $this->fail('abort() must throw');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('Admins only', $e->getMessage());
            $this->assertSame(['X-Test' => '1'], $e->getHeaders());
        }

        abort_if(false, 500);
        abort_unless(true, 500);

        $this->expectException(HttpException::class);
        abort_unless(false, 404);
    }

    public function testAbortIfThrowsWhenTrue() {
        $this->expectException(HttpException::class);
        abort_if(true, 409, 'Conflict');
    }
}
