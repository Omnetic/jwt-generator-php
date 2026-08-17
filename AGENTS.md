# omnetic-jwt-generator-php

PHP SDK that generates signed **RS256** JWT tokens for authenticating Omnetic DMS
**Service Account (SA)** requests against the DMS API. Distributed to third parties as a
standalone library — installed directly from GitHub, no public package registry (v1).

> **Status:** the SA token generator is implemented — `generateToken()` produces an
> RS256-signed JWT (see Public API). The other-language SDKs (C#/Python/TypeScript) live in
> separate repos.

## Tech Stack

- **Library floor:** PHP **8.2+** with `ext-openssl` (what consumers need).
- **Runtime dependency:** `firebase/php-jwt ^7.0` for RS256 signing — the same JWT library
  the DMS monorepo uses.
- **Dev/test image:** PHP **8.4** (pinned in the Dockerfile).
- **Testing:** PHPUnit 12. **Static analysis:** PHPStan 2.x (level max).
- **Code style:** ECS (Easy Coding Standard) — mirrors the monorepo rule set (PSR-12 +
  array / clean-code / strict, plus Slevomat type-hint & comment sniffs). Pinned to
  PHP_CodeSniffer 3.x (Slevomat is not runtime-compatible with PHPCS 4).
- **Dependency manager:** Composer 2. **Local runtime:** Docker + Docker Compose.

## Layout

```
src/JwtGenerator.php   ← public API; `Omnetic\JwtGenerator\` namespace
tests/                 ← PHPUnit suite; `Omnetic\JwtGenerator\Tests\` namespace
examples/              ← runnable CLI usage examples (global namespace, no autoload PSR-4)
Dockerfile             ← php:8.4-cli-alpine + composer binary
docker-compose.yml     ← single `php` service, mounts the repo, no ports
Makefile               ← dev entry points (wrap `docker compose run --rm php …`)
phpunit.xml.dist       ← test config
phpstan.neon.dist      ← static-analysis config
ecs.php                ← code-style config (ECS)
```

## Development

Everything runs inside Docker — no local PHP required.

```bash
make build      # build the dev/test image
make install    # composer install
make test       # run PHPUnit
make stan       # run PHPStan
make cs         # check code style (ECS)
make cs-fix     # auto-fix code style (ECS)
make shell      # open a shell in the container

# Runnable examples (the key must sit inside the repo — that is what gets mounted)
make example         KEY=./sa-key.pem KID=<kid> [LIFETIME=600]
make example-request KEY=./sa-key.pem KID=<kid> URL=https://<dms-host>/<endpoint>
```

Equivalent without `make`: `docker compose run --rm php <cmd>` (e.g.
`docker compose run --rm php vendor/bin/phpunit`).

## Public API

```php
use Omnetic\JwtGenerator\JwtGenerator;

$token = JwtGenerator::generateToken($privateKey, $kid, $lifetime = 3600);
```

- `$privateKey` — RSA private key in **PEM** format (issued on SA creation / key rotation).
- `$kid` — key ID (issued alongside the key); becomes the JWT `sub` claim.
- `$lifetime` — token validity in seconds, optional, default `3600`, **max `3600`**.
- Returns a signed JWT for the `Authorization: Bearer` header.

The signed token uses **RS256** with header `{"alg":"RS256","typ":"JWT","kid":"<kid>"}`
(the `kid` is carried in **both** the header and the `sub` claim) and these payload claims:

```json
{ "type": "sa", "sub": "<kid>", "iat": "<now>", "exp": "<now + lifetime>" }
```

Validation the SDK enforces (cheap argument checks first, key parsing last): non-empty
`kid`, `1 <= lifetime <= 3600`, non-empty key, valid RSA PEM of at least 2048 bits.

## Conventions

- `declare(strict_types=1);` in every PHP file.
- PSR-4 autoloading; classes `final` unless extension is a deliberate part of the API.
- Import class references (no fully-qualified `\Foo` in code) — enforced by ECS.
- Signing goes through `firebase/php-jwt` (RS256); `ext-openssl` provides the crypto.
- Every public behavior gets a PHPUnit test; `make test`, `make stan`, and `make cs` must stay green.
- `examples/` is analysed by PHPStan and ECS too — keep the scripts runnable, self-contained
  (no shared bootstrap) and free of hard-coded DMS hosts. They must also run from a Composer
  install, hence the two-path `require` of `autoload.php`; `tests/GenerateTokenExampleTest.php`
  pins their exit codes and argument handling.

## Context

- Jira: **T20-127460** ("[BE] JWT Generator SDK — C#, Python, TypeScript, PHP").
- Tokens must pass Gateway validation — **UC10 in T20-120653**. The claim shape and RS256
  algorithm above are the contract; do not diverge from them.
- The token-type claim is **`type`** (value `sa`), per the gateway contract (DEV-2012,
  confirmed in MR !13706). The legacy `typ` claim is deprecated — do **not** emit it, even
  though UC10's older example still shows `typ`.
- Sibling SDKs (separate repos): `omnetic-jwt-generator-csharp`,
  `omnetic-jwt-generator-python`, `omnetic-jwt-generator-typescript`.
