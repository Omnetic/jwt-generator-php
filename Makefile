DC = docker compose run --rm php

.PHONY: build install test stan cs cs-fix example example-request shell

# Build the dev/test image
build:
	docker compose build

# Install Composer dependencies
install:
	$(DC) composer install

# Run the PHPUnit test suite
test:
	$(DC) vendor/bin/phpunit

# Run PHPStan static analysis (its workers need more than the image's 128M default)
stan:
	$(DC) vendor/bin/phpstan analyse --memory-limit=512M

# Check code style (ECS)
cs:
	$(DC) vendor/bin/ecs check

# Fix code style (ECS)
cs-fix:
	$(DC) vendor/bin/ecs check --fix

# Print a signed SA token: make example KEY=./sa-key.pem KID=<kid> [LIFETIME=3600]
example:
	$(DC) php examples/generate-token.php "$(KEY)" "$(KID)" $(LIFETIME)

# Call a DMS endpoint with a signed SA token: make example-request KEY=./sa-key.pem KID=<kid> URL=<url>
example-request:
	$(DC) php examples/call-dms-api.php "$(KEY)" "$(KID)" "$(URL)"

# Open a shell in the container
shell:
	$(DC) sh
