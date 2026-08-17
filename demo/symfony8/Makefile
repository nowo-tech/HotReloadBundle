.PHONY: help up down build install shell logs test test-coverage cache-clear update-bundle ensure-up restart verify assets

# Prefer Compose V2; resolve docker via absolute path so GNU Make does not hit a local ./docker/ dir when PATH has empty entries (REQ-MAKE-010).
DOCKER_BIN := $(shell command -v docker 2>/dev/null)
COMPOSE := $(if $(DOCKER_BIN),$(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose"),docker-compose)

help:
	@echo "Symfony 8 Demo - Console Debug Bundle"
	@echo "  make up             Start containers + install + build assets"
	@echo "  make down           Stop containers"
	@echo "  make restart        Restart containers"
	@echo "  make build          Build image (no cache)"
	@echo "  make install        Install Composer + pnpm dependencies"
	@echo "  make assets         pnpm install + Vite build (public/build/)"
	@echo "  make shell          Open shell in php container"
	@echo "  make logs           Stream container logs"
	@echo "  make test           Run tests"
	@echo "  make test-coverage  Run tests with coverage"
	@echo "  make update-bundle  Update nowo-tech/hot-reload-bundle and clear cache"

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	$(COMPOSE) build
	@echo "Installing Composer dependencies (before FrankenPHP worker boot)..."
	@n=0; \
	cache_vol=""; \
	if [ -n "$$COMPOSER_CACHE_DIR" ]; then \
		mkdir -p "$$COMPOSER_CACHE_DIR"; \
		cache_vol="-v $$COMPOSER_CACHE_DIR:/root/.composer/cache"; \
	fi; \
	until $(COMPOSE) run --rm --no-deps \
		-e COMPOSER_MEMORY_LIMIT=-1 \
		-e COMPOSER_AUTH \
		-e COMPOSER_MAX_PARALLEL_HTTP=$${COMPOSER_MAX_PARALLEL_HTTP:-1} \
		$$cache_vol \
		--entrypoint composer php install --no-interaction --no-dev --prefer-install=auto; do \
		n=$$((n+1)); \
		if [ $$n -ge 8 ]; then echo "composer install failed after $$n attempts"; exit 1; fi; \
		sleep_s=$$((15 * n)); \
		if [ $$sleep_s -gt 120 ]; then sleep_s=120; fi; \
		echo "composer install failed (attempt $$n/8), retrying in $${sleep_s}s..."; \
		sleep $$sleep_s; \
	done
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@$(COMPOSE) exec -T php php bin/console assets:install public --symlink --no-interaction 2>/dev/null || true
	@$(MAKE) assets
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"
ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Starting demo container..."; \
		$(COMPOSE) up -d; \
		sleep 3; \
	fi

down:
	$(COMPOSE) down

restart: ensure-up
	$(COMPOSE) restart

build:
	$(COMPOSE) build --no-cache

install: ensure-up
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	$(COMPOSE) exec -T php php bin/console assets:install public --symlink --no-interaction 2>/dev/null || true
	$(COMPOSE) exec -T php sh -c 'test -f package.json && pnpm install || true'

assets: ensure-up
	$(COMPOSE) exec -T php sh -c 'pnpm install && pnpm run build'

shell: ensure-up
	$(COMPOSE) exec php sh

logs:
	$(COMPOSE) logs -f

test: ensure-up
	$(COMPOSE) exec -T -e APP_ENV=test php php bin/phpunit

test-coverage: ensure-up
	$(COMPOSE) exec -T -e APP_ENV=test php php bin/phpunit --coverage-text

cache-clear: ensure-up
	$(COMPOSE) exec -T php php bin/console cache:clear

update-bundle: ensure-up
	@if [ ! -f composer.lock ]; then \
		echo "No composer.lock — running full composer update..."; \
		$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer update --no-interaction; \
	else \
		$(COMPOSE) exec -T php composer update nowo-tech/hot-reload-bundle --no-interaction; \
	fi
	$(COMPOSE) exec -T php php bin/console cache:clear --env=dev

verify: ensure-up
	@code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$${PORT:-8011} 2>/dev/null || echo "000"); \
	if [ "$$code" != "200" ]; then echo "Healthcheck failed: $$code"; exit 1; fi; \
	echo "Healthcheck OK ($$code)"


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
SERVICE_PHP := php
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
