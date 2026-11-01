<?php

use PHPUnit\Framework\TestCase;

use JHz\Security\JWT;

class JWTTest extends TestCase {
    const SECRET = 'a-test-secret-that-is-at-least-32-characters-long';

    private function jwt() {
        return new JWT(self::SECRET);
    }

    public function testSignAndVerifyRoundTrip() {
        $token = $this->jwt()->sign(['sub' => 7, 'exp' => time() + 60]);
        $result = $this->jwt()->verify($token);
        $this->assertTrue($result['valid']);
        $this->assertSame(7, $result['payload']['sub']);
    }

    public function testExpiredTokenIsRejected() {
        $token = $this->jwt()->sign(['sub' => 1, 'exp' => time() - 1000]);
        $result = $this->jwt()->verify($token);
        $this->assertFalse($result['valid']);
        $this->assertSame('Token expired', $result['reason']);
    }

    public function testTokenWithoutExpiryIsRejected() {
        $token = $this->jwt()->sign(['sub' => 1]);
        $result = $this->jwt()->verify($token);
        $this->assertFalse($result['valid']);
        $this->assertSame('Token has no expiry', $result['reason']);
    }

    public function testTamperedPayloadIsRejected() {
        $token = $this->jwt()->sign(['sub' => 1, 'exp' => time() + 60]);
        list($h, $p, $s) = explode('.', $token);
        $forged = rtrim(strtr(base64_encode(json_encode(['sub' => 2, 'exp' => time() + 60, 'iat' => time()])), '+/', '-_'), '=');
        $result = $this->jwt()->verify("$h.$forged.$s");
        $this->assertFalse($result['valid']);
    }

    public function testTokenSignedWithAnotherSecretIsRejected() {
        $other = new JWT('another-secret-that-is-also-32-characters-or-more');
        $token = $other->sign(['sub' => 1, 'exp' => time() + 60]);
        $this->assertFalse($this->jwt()->verify($token)['valid']);
    }

    public function testGarbageIsRejected() {
        $this->assertFalse($this->jwt()->verify('not-a-token')['valid']);
        $this->assertFalse($this->jwt()->verify('')['valid']);
    }

    public function testAlgNoneIsRejected() {
        $enc = function ($d) {
            return rtrim(strtr(base64_encode(json_encode($d)), '+/', '-_'), '=');
        };
        $token = $enc(['alg' => 'none', 'typ' => 'JWT']) . '.' . $enc(['sub' => 1, 'exp' => time() + 60]) . '.';
        $this->assertFalse($this->jwt()->verify($token)['valid']);
    }

    public function testShortSecretIsRejected() {
        $this->expectException(Exception::class);
        new JWT('too-short');
    }

    public function testExampleSecretIsRejected() {
        $this->expectException(Exception::class);
        new JWT('your-super-secret-key-at-least-32-chars-long');
    }
}
