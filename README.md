# omnetic-jwt-generator-php

PHP SDK for generating signed **RS256** JWT tokens used to authenticate Omnetic DMS
**Service Account** requests against the DMS API.

## Requirements

- PHP **8.2+** with the `openssl` extension (for consumers)
- Docker + Docker Compose (for local development)

## Usage

```php
use Omnetic\JwtGenerator\JwtGenerator;

$token = JwtGenerator::generateToken($privateKey, $kid, 3600);
// Authorization: Bearer <token>
```

- `$privateKey` — RSA private key in PEM format (issued on SA creation / key rotation)
- `$kid` — key ID (issued alongside the key)
- `$lifetime` — token validity in seconds, optional, default `3600`, max `3600`

`generateToken()` builds and RS256-signs a JWT with header
`{"alg":"RS256","typ":"JWT","kid":"<kid>"}` and the claims:

```json
{ "type": "sa", "sub": "<kid>", "iat": "<now>", "exp": "<now + lifetime>" }
```

It throws `InvalidArgumentException` if the `kid` is empty, `lifetime` is outside `1…3600`,
or the private key is empty / not a valid RSA PEM / shorter than 2048 bits.

## Examples

Two runnable scripts in [`examples/`](examples) — both take the key path, the `kid` and
(for the request example) the endpoint URL as arguments, or from the `OMNETIC_SA_KEY_PATH`,
`OMNETIC_SA_KID` and `OMNETIC_DMS_API_URL` environment variables.

```bash
# Print a signed token (default lifetime 3600 s; optional third argument overrides it)
php examples/generate-token.php ./sa-key.pem <kid> 600

# Call a DMS endpoint with a freshly signed token — prints the status and body
php examples/call-dms-api.php ./sa-key.pem <kid> https://<dms-host>/<endpoint>
```

They also run straight from a Composer install of the SDK, no clone needed:

```bash
php vendor/omnetic/jwt-generator/examples/generate-token.php ./sa-key.pem <kid>
```

Pass the path to the key, never the key itself, so no key material lands in your shell
history. Both scripts write the token / response to stdout and every diagnostic to stderr,
and exit non-zero on failure (`call-dms-api.php` exits `0` on any `2xx`), so they compose
in scripts and CI.

Use the URL of a DMS endpoint your Service Account is allowed to call; the example does
not assume one. A `401` means the Gateway rejected the token — check that the `kid` matches
the key, that the Service Account is enabled, and that the clock is not skewed.
`call-dms-api.php` needs `ext-curl` (enabled in the official PHP images); the SDK itself
needs only `ext-openssl`.

Via Docker, wrapped by the Makefile (the key must sit inside the repo — that is what gets
mounted; `*.pem` is git-ignored):

```bash
make example         KEY=./sa-key.pem KID=<kid> [LIFETIME=600]
make example-request KEY=./sa-key.pem KID=<kid> URL=https://<dms-host>/<endpoint>
```

The three environment variables are forwarded into the container, so they work as fallbacks
for the `make` targets too.

## Development

Everything runs inside Docker — no local PHP required.

```bash
make build      # build the dev/test image (PHP 8.4)
make install    # composer install
make test       # run PHPUnit
make stan       # run PHPStan
make cs         # check code style (ECS)
make cs-fix     # auto-fix code style (ECS)
make shell      # open a shell in the container
```
