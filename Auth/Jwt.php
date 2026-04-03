<?php

namespace SwiftPHP\Core\Auth;

use Exception;

class Jwt
{
    protected static $secret = '';
    protected static $algorithm = 'HS256';
    protected static $ttl = 7200;
    protected static $issuer = '';
    protected static $audience = '';

    public static function init(array $config = []): void
    {
        self::$secret = $config['secret'] ?? 'swiftphp_secret_key';
        self::$algorithm = $config['algorithm'] ?? 'HS256';
        self::$ttl = $config['ttl'] ?? 7200;
        self::$issuer = $config['issuer'] ?? '';
        self::$audience = $config['audience'] ?? '';
    }

    public static function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm,
        ];

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $payloadEncoded = self::addStandardClaims($payload);

        $payloadEncodedStr = json_encode($payloadEncoded);
        $payloadEncoded = self::base64UrlEncode($payloadEncodedStr);

        $signature = self::sign($headerEncoded . '.' . $payloadEncoded);
        $signatureEncoded = self::base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    protected static function addStandardClaims(array $payload): array
    {
        $now = time();

        $payload['iat'] = $now;
        $payload['exp'] = $now + self::$ttl;
        $payload['nbf'] = $now;

        if (!empty(self::$issuer)) {
            $payload['iss'] = self::$issuer;
        }

        if (!empty(self::$audience)) {
            $payload['aud'] = self::$audience;
        }

        return $payload;
    }

    protected static function sign(string $data): string
    {
        switch (self::$algorithm) {
            case 'HS256':
                return hash_hmac('sha256', $data, self::$secret, true);
            case 'HS384':
                return hash_hmac('sha384', $data, self::$secret, true);
            case 'HS512':
                return hash_hmac('sha512', $data, self::$secret, true);
            default:
                throw new Exception("Unsupported algorithm: " . self::$algorithm);
        }
    }

    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        $header = json_decode(self::base64UrlDecode($headerEncoded), true);
        if (!$header || !isset($header['alg'])) {
            return null;
        }

        $signature = self::base64UrlDecode($signatureEncoded);
        $expectedSignature = self::sign($headerEncoded . '.' . $payloadEncoded);

        if (!self::constantTimeCompare($signature, $expectedSignature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        if (!$payload) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return null;
        }

        if (isset($payload['iss']) && !empty(self::$issuer) && $payload['iss'] !== self::$issuer) {
            return null;
        }

        if (isset($payload['aud']) && !empty(self::$audience) && $payload['aud'] !== self::$audience) {
            return null;
        }

        unset($payload['iat'], $payload['exp'], $payload['nbf'], $payload['iss'], $payload['aud']);

        return $payload;
    }

    public static function check(): bool
    {
        $token = self::getTokenFromHeader();
        if (!$token) {
            return false;
        }

        $payload = self::decode($token);
        return $payload !== null;
    }

    public static function getTokenFromHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }

        return null;
    }

    public static function getPayload(?string $key = null, $default = null)
    {
        $token = self::getTokenFromHeader();
        if (!$token) {
            return $default;
        }

        $payload = self::decode($token);
        if (!$payload) {
            return $default;
        }

        if ($key === null) {
            return $payload;
        }

        return $payload[$key] ?? $default;
    }

    public static function refresh(?int $ttl = null): ?string
    {
        $payload = self::getPayload();
        if (!$payload) {
            return null;
        }

        if ($ttl !== null) {
            $oldTtl = self::$ttl;
            self::$ttl = $ttl;
            $newToken = self::encode($payload);
            self::$ttl = $oldTtl;
            return $newToken;
        }

        return self::encode($payload);
    }

    protected static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    protected static function constantTimeCompare(string $known, string $user): bool
    {
        if (strlen($known) !== strlen($user)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < strlen($known); $i++) {
            $result |= ord($known[$i]) ^ ord($user[$i]);
        }

        return $result === 0;
    }
}
