<?php

/**
 * Sign a Service Account token and print it.
 *
 * Usage:
 *   php examples/generate-token.php <path-to-private-key.pem> <kid> [lifetime]
 *
 * The key path and kid may also come from the OMNETIC_SA_KEY_PATH and
 * OMNETIC_SA_KID environment variables. Both the key and the kid are issued on
 * Service Account creation / key rotation.
 */

declare(strict_types=1);

use Omnetic\JwtGenerator\JwtGenerator;

// Runs both from a clone (vendor/ at the repo root) and from a Composer install,
// where this file sits in vendor/omnetic/jwt-generator/examples/.
require is_file(__DIR__ . '/../vendor/autoload.php')
    ? __DIR__ . '/../vendor/autoload.php'
    : __DIR__ . '/../../../autoload.php';

// Empty arguments fall through to the environment, so an omitted `make example`
// variable behaves the same as passing nothing at all.
$keyPath = ($argv[1] ?? '') ?: getenv('OMNETIC_SA_KEY_PATH');
$kid = ($argv[2] ?? '') ?: getenv('OMNETIC_SA_KID');
$lifetime = isset($argv[3]) ? (int) $argv[3] : 3600;

if (!is_string($keyPath) || $keyPath === '' || !is_string($kid) || $kid === '') {
    fwrite(STDERR, "Usage: php examples/generate-token.php <path-to-private-key.pem> <kid> [lifetime]\n");

    exit(1);
}

if (!is_file($keyPath) || !is_readable($keyPath)) {
    fwrite(STDERR, sprintf("The private key file %s does not exist or is not readable.\n", $keyPath));

    exit(1);
}

try {
    $token = JwtGenerator::generateToken((string) file_get_contents($keyPath), $kid, $lifetime);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, sprintf("Could not generate a token: %s\n", $exception->getMessage()));

    exit(1);
}

// Send this as `Authorization: Bearer <token>` on every DMS API request.
echo $token, "\n";
