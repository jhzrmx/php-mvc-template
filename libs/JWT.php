<?php

/**
 * @package JWT
 * @author jhzrmx
 * @version 1.0.0
 * @license MIT
 * @link https://github.com/jhzrmx/php-mvc-template
 */
class JWT {
    private $secret;
    private $leeway = 60;

    private $header = array(
        'alg' => 'HS256',
        'typ' => 'JWT'
    );

    /**
     * Constructor for the JWT class.
     *
     * @param string $secret The secret key for the JWT.
     * @return void
     * @throws Exception If the secret is not set or is less than 32 characters.
     */
    public function __construct($secret) {
        if (empty($secret) || strlen($secret) < 32) {
            throw new Exception("JWT secret must be at least 32 characters.");
        }
        $this->secret = $secret;
    }

    /**
     * Encode a string to base64url.
     *
     * @param string $data The data to encode.
     * @return string The encoded data.
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decode a string from base64url.
     *
     * @param string $data The data to decode.
     * @return string The decoded data.
     */
    private function base64UrlDecode($data) {
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Sign a payload with the JWT.
     *
     * @param array $payload The payload to sign.
     * @return string The signed JWT.
     * @throws Exception If the payload is not an array.
     */
    public function sign($payload) {
        if (!is_array($payload)) {
            throw new Exception("Payload must be an array.");
        }

        if (!isset($payload['iat'])) {
            $payload['iat'] = time();
        }

        $headerEncoded = $this->base64UrlEncode(
            json_encode($this->header)
        );

        $payloadEncoded = $this->base64UrlEncode(
            json_encode($payload)
        );

        $signature = hash_hmac(
            'sha256',
            $headerEncoded . "." . $payloadEncoded,
            $this->secret,
            true
        );

        $signatureEncoded = $this->base64UrlEncode($signature);
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }

    /**
     * Verify a JWT.
     *
     * @param string $jwt The JWT to verify.
     * @return array The result of the verification.
     * @throws Exception If the JWT is not valid.
     */
    public function verify($jwt) {
        if (empty($jwt)) {
            return array(
                'valid' => false,
                'reason' => 'Token missing'
            );
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return array(
                'valid' => false,
                'reason' => 'Invalid token format'
            );
        }

        $headerEncoded = $parts[0];
        $payloadEncoded = $parts[1];
        $signatureEncoded = $parts[2];

        $header = json_decode($this->base64UrlDecode($headerEncoded), true);
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!is_array($header) || !is_array($payload)) {
            return array(
                'valid' => false,
                'reason' => 'Invalid JSON'
            );
        }

        if (!isset($header['alg']) || $header['alg'] !== 'HS256') {
            return array(
                'valid' => false,
                'reason' => 'Unsupported algorithm'
            );
        }

        $validSignature = hash_hmac(
            'sha256',
            $headerEncoded . "." . $payloadEncoded,
            $this->secret,
            true
        );

        $providedSignature = $this->base64UrlDecode($signatureEncoded);
        if (!hash_equals($validSignature, $providedSignature)) {
            return array(
                'valid' => false,
                'reason' => 'Invalid signature'
            );
        }

        $now = time();
        if (isset($payload['nbf']) && ($payload['nbf'] - $this->leeway) > $now) {
            return array(
                'valid' => false,
                'reason' => 'Token not active yet'
            );
        }

        if (isset($payload['iat']) && ($payload['iat'] - $this->leeway) > $now) {
            return array(
                'valid' => false,
                'reason' => 'Token issued in future'
            );
        }

        if (isset($payload['exp']) && ($payload['exp'] + $this->leeway) < $now) {
            return array(
                'valid' => false,
                'reason' => 'Token expired'
            );
        }

        return array(
            'valid' => true,
            'reason' => 'Token validated',
            'payload' => $payload
        );
    }

    /**
     * Decode a JWT.
     *
     * @param string $jwt The JWT to decode.
     * @return array The decoded payload.
     * @throws Exception If the JWT is not valid.
     */
    public function decode($jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        return json_decode($this->base64UrlDecode($parts[1]), true);
    }
}