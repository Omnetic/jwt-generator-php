<?php

/**
 * Sign a Service Account token and call a DMS API endpoint with it.
 *
 * Usage:
 *   php examples/call-dms-api.php <path-to-private-key.pem> <kid> <url>
 *
 * The arguments may also come from the OMNETIC_SA_KEY_PATH, OMNETIC_SA_KID and
 * OMNETIC_DMS_API_URL environment variables. Use the URL of the DMS endpoint
 * your Service Account is allowed to call.
 *
 * Needs ext-curl (shipped enabled in the official PHP images) — the SDK itself
 * needs only ext-openssl.
 */

declare(strict_types=1);

use Omnetic\JwtGenerator\JwtGenerator;

// Runs both from a clone (vendor/ at the repo root) and from a Composer install,
// where this file sits in vendor/omnetic/jwt-generator/examples/.
require is_file(__DIR__ . '/../vendor/autoload.php')
    ? __DIR__ . '/../vendor/autoload.php'
    : __DIR__ . '/../../../autoload.php';

// Empty arguments fall through to the environment, so an omitted `make
// example-request` variable behaves the same as passing nothing at all.
$keyPath = ($argv[1] ?? '') ?: getenv('OMNETIC_SA_KEY_PATH');
$kid = ($argv[2] ?? '') ?: getenv('OMNETIC_SA_KID');
$url = ($argv[3] ?? '') ?: getenv('OMNETIC_DMS_API_URL');

if (
    !is_string($keyPath) || $keyPath === ''
    || !is_string($kid) || $kid === ''
    || !is_string($url) || $url === ''
) {
    fwrite(STDERR, "Usage: php examples/call-dms-api.php <path-to-private-key.pem> <kid> <url>\n");

    exit(1);
}

if (!is_file($keyPath) || !is_readable($keyPath)) {
    fwrite(STDERR, sprintf("The private key file %s does not exist or is not readable.\n", $keyPath));

    exit(1);
}

if (!str_starts_with($url, 'https://')) {
    fwrite(STDERR, sprintf("Warning: %s is not HTTPS — the token would travel in plaintext.\n", $url));
}

try {
    // A short lifetime is enough for a single request; tokens are cheap to mint.
    $token = JwtGenerator::generateToken((string) file_get_contents($keyPath), $kid, 300);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, sprintf("Could not generate a token: %s\n", $exception->getMessage()));

    exit(1);
}

$handle = curl_init($url);
if ($handle === false) {
    fwrite(STDERR, sprintf("Could not initialise a request to %s.\n", $url));

    exit(1);
}

curl_setopt_array($handle, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ],
]);

$body = curl_exec($handle);
$status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
$error = curl_error($handle);
curl_close($handle);

if (!is_string($body)) {
    fwrite(STDERR, sprintf("The request to %s failed: %s\n", $url, $error));

    exit(1);
}

printf("HTTP %d\n%s\n", $status, $body);

// A 401 means the Gateway rejected the token: check that the kid matches the
// key, that the Service Account is enabled, and that the clock is not skewed.
exit($status >= 200 && $status < 300 ? 0 : 1);
