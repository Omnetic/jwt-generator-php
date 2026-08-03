<?php

declare(strict_types=1);

namespace Omnetic\JwtGenerator\Tests;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use InvalidArgumentException;
use Omnetic\JwtGenerator\JwtGenerator;
use PHPUnit\Framework\TestCase;

final class JwtGeneratorTest extends TestCase
{
    private const KID = 'kid-uuid-1';

    public function testReturnsCompactJwtWithThreeSegments(): void
    {
        $keys = $this->generateRsaKeyPair();

        $token = JwtGenerator::generateToken($keys['private'], self::KID);

        self::assertCount(3, explode('.', $token));
    }

    public function testHeaderUsesRs256AndCarriesTheKid(): void
    {
        $keys = $this->generateRsaKeyPair();

        $token = JwtGenerator::generateToken($keys['private'], self::KID);
        $header = $this->decodeSegment($token, 0);

        self::assertSame('RS256', $header['alg']);
        self::assertSame('JWT', $header['typ']);
        self::assertSame(self::KID, $header['kid']);
    }

    public function testPayloadCarriesServiceAccountClaims(): void
    {
        $keys = $this->generateRsaKeyPair();

        $before = time();
        $token = JwtGenerator::generateToken($keys['private'], self::KID, 1800);
        $after = time();
        $payload = $this->decodeSegment($token, 1);

        self::assertSame('sa', $payload['type']);
        self::assertSame(self::KID, $payload['sub']);
        self::assertArrayNotHasKey('typ', $payload);

        $issuedAt = $payload['iat'];
        $expiresAt = $payload['exp'];
        assert(is_int($issuedAt));
        assert(is_int($expiresAt));
        self::assertGreaterThanOrEqual($before, $issuedAt);
        self::assertLessThanOrEqual($after, $issuedAt);
        self::assertSame($issuedAt + 1800, $expiresAt);
    }

    public function testDefaultLifetimeIsOneHour(): void
    {
        $keys = $this->generateRsaKeyPair();

        $token = JwtGenerator::generateToken($keys['private'], self::KID);
        $payload = $this->decodeSegment($token, 1);

        $issuedAt = $payload['iat'];
        $expiresAt = $payload['exp'];
        assert(is_int($issuedAt));
        assert(is_int($expiresAt));
        self::assertSame(3600, $expiresAt - $issuedAt);
    }

    public function testSignatureVerifiesWithMatchingPublicKey(): void
    {
        $keys = $this->generateRsaKeyPair();

        $token = JwtGenerator::generateToken($keys['private'], self::KID);
        $decoded = JWT::decode($token, new Key($keys['public'], 'RS256'));

        self::assertSame('sa', $decoded->type);
    }

    public function testSignatureIsRejectedByADifferentPublicKey(): void
    {
        $signing = $this->generateRsaKeyPair();
        $other = $this->generateRsaKeyPair();

        $token = JwtGenerator::generateToken($signing['private'], self::KID);

        $this->expectException(SignatureInvalidException::class);
        JWT::decode($token, new Key($other['public'], 'RS256'));
    }

    public function testRejectsEmptyPrivateKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JwtGenerator::generateToken('', self::KID);
    }

    public function testRejectsMalformedPrivateKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JwtGenerator::generateToken('not a pem key', self::KID);
    }

    public function testRejectsNonRsaPrivateKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JwtGenerator::generateToken($this->generateEcPrivateKey(), self::KID);
    }

    public function testRejectsRsaKeyShorterThan2048Bits(): void
    {
        $keys = $this->generateRsaKeyPair(1024);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 2048 bits');
        JwtGenerator::generateToken($keys['private'], self::KID);
    }

    public function testValidatesKidBeforeParsingThePrivateKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The kid must not be empty.');
        JwtGenerator::generateToken('not a pem key', '');
    }

    public function testValidatesLifetimeBeforeParsingThePrivateKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The lifetime must be between 1 and 3600 seconds');
        JwtGenerator::generateToken('not a pem key', self::KID, 3601);
    }

    public function testRejectsEmptyKid(): void
    {
        $keys = $this->generateRsaKeyPair();

        $this->expectException(InvalidArgumentException::class);
        JwtGenerator::generateToken($keys['private'], '');
    }

    public function testRejectsLifetimeAboveMaximum(): void
    {
        $keys = $this->generateRsaKeyPair();

        $this->expectException(InvalidArgumentException::class);
        JwtGenerator::generateToken($keys['private'], self::KID, 3601);
    }

    public function testRejectsNonPositiveLifetime(): void
    {
        $keys = $this->generateRsaKeyPair();

        $this->expectException(InvalidArgumentException::class);
        JwtGenerator::generateToken($keys['private'], self::KID, 0);
    }

    /**
     * @return array{private: string, public: string}
     */
    private function generateRsaKeyPair(int $bits = 2048): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            self::fail('Could not generate an RSA key pair: ' . openssl_error_string());
        }

        openssl_pkey_export($resource, $privateKey);
        assert(is_string($privateKey));

        $details = openssl_pkey_get_details($resource);
        if ($details === false) {
            self::fail('Could not read the generated RSA key details.');
        }

        $publicKey = $details['key'];
        assert(is_string($publicKey));

        return [
            'private' => $privateKey,
            'public' => $publicKey,
        ];
    }

    private function generateEcPrivateKey(): string
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if ($resource === false) {
            self::fail('Could not generate an EC key pair: ' . openssl_error_string());
        }

        openssl_pkey_export($resource, $privateKey);
        assert(is_string($privateKey));

        return $privateKey;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeSegment(string $token, int $index): array
    {
        $segments = explode('.', $token);
        $decoded = json_decode(JWT::urlsafeB64Decode($segments[$index]), true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
