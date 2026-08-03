<?php

declare(strict_types=1);

namespace Omnetic\JwtGenerator;

use Firebase\JWT\JWT;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;

final class JwtGenerator
{
    private const ALGORITHM = 'RS256';

    private const TOKEN_TYPE = 'sa';

    private const MAX_LIFETIME = 3600;

    private const MIN_KEY_BITS = 2048;

    /**
     * Build a signed RS256 JWT for authenticating a Service Account against the DMS API.
     *
     * @param string $privateKey RSA private key in PEM format
     * @param string $kid Key ID; carried in both the JWT header and the `sub` claim
     * @param int $lifetime Token validity in seconds (1–3600)
     * @return string Signed JWT ready for the `Authorization: Bearer` header
     */
    public static function generateToken(string $privateKey, string $kid, int $lifetime = self::MAX_LIFETIME): string
    {
        if ($kid === '') {
            throw new InvalidArgumentException('The kid must not be empty.');
        }

        if ($lifetime < 1 || $lifetime > self::MAX_LIFETIME) {
            throw new InvalidArgumentException(
                sprintf('The lifetime must be between 1 and %d seconds, got %d.', self::MAX_LIFETIME, $lifetime),
            );
        }

        $key = self::parseRsaPrivateKey($privateKey);

        $issuedAt = time();
        $payload = [
            'type' => self::TOKEN_TYPE,
            'sub' => $kid,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $lifetime,
        ];

        return JWT::encode($payload, $key, self::ALGORITHM, $kid);
    }

    private static function parseRsaPrivateKey(string $privateKey): OpenSSLAsymmetricKey
    {
        if ($privateKey === '') {
            throw new InvalidArgumentException('The private key must not be empty.');
        }

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new InvalidArgumentException('The private key is not a valid PEM-encoded key.');
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new InvalidArgumentException('The private key must be an RSA key.');
        }

        $bits = $details['bits'] ?? 0;
        if (!is_int($bits) || $bits < self::MIN_KEY_BITS) {
            throw new InvalidArgumentException(
                sprintf('The RSA private key must be at least %d bits.', self::MIN_KEY_BITS),
            );
        }

        return $key;
    }
}
