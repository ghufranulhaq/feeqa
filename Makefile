.DEFAULT_GOAL := help
.PHONY: help up down restart logs shell install key migrate fresh test test-filter \
        test-browser ci lint pint stan hooks demo-reset docs-check

COMPOSE := docker compose

# Overrides for anything that must differ from the container's real .env
# during tests. Passed at the `exec` level, not left to phpunit.xml's
# <env force="true">: the container's env_file already sets these as real
# process environment variables, which PHP's CLI SAPI copies into $_SERVER —
# and PHPUnit's env-forcing only touches getenv()/putenv()/$_ENV, never
# $_SERVER, so a stale $_SERVER value wins unless overridden here too.
TEST_ENV := -e APP_ENV=testing -e DB_DATABASE=feeqa_test -e CACHE_STORE=array \
            -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync -e MAIL_MAILER=array \
            -e PASSWORD_CHECK_BREACHED=false

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*## "}{printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

up: ## Start the local stack (Docker)
	$(COMPOSE) up -d

down: ## Stop the local stack
	$(COMPOSE) down

restart: ## Restart every container
	$(COMPOSE) restart

logs: ## Follow logs for every container (or: make logs S=app)
	$(COMPOSE) logs -f $(S)

shell: ## Open a shell in the app container
	$(COMPOSE) exec app bash

install: ## Install PHP and JS dependencies inside the containers
	$(COMPOSE) exec app composer install
	$(COMPOSE) exec app npm install
	$(COMPOSE) exec app php artisan storage:link

key: ## Generate the application key
	$(COMPOSE) exec app php artisan key:generate

migrate: ## Run database migrations
	$(COMPOSE) exec app php artisan migrate

fresh: ## Drop all tables and re-run migrations (local only — asks to confirm)
	$(COMPOSE) exec app php artisan migrate:fresh

test: ## Run the Pest test suite
	$(COMPOSE) exec $(TEST_ENV) app ./vendor/bin/pest

test-filter: ## Run tests matching a filter: make test-filter F=SomeTest
	$(COMPOSE) exec $(TEST_ENV) app ./vendor/bin/pest --filter=$(F)

test-browser: ## Run Playwright-backed browser/accessibility tests
	$(COMPOSE) exec $(TEST_ENV) app ./vendor/bin/pest --group=browser

ci: lint stan test docs-check ## Everything the pre-push hook runs

lint: ## Pint (PHP) + ESLint/Prettier (JS/TS) + tsc
	$(COMPOSE) exec app ./vendor/bin/pint --test
	$(COMPOSE) exec app npm run lint
	$(COMPOSE) exec app npx tsc --noEmit

pint: ## Auto-fix PHP formatting
	$(COMPOSE) exec app ./vendor/bin/pint

stan: ## Static analysis (Larastan)
	$(COMPOSE) exec app ./vendor/bin/phpstan analyse

hooks: ## Enable the pre-push git hook (runs `make ci` before every push)
	git config core.hooksPath .githooks
	chmod +x .githooks/pre-push

demo-reset: ## Re-run migrations with base + demo seeders (asks to confirm)
	$(COMPOSE) exec app php artisan migrate:fresh --seed --seeder=Database\\Seeders\\DemoDatabaseSeeder

docs-check: ## Verify README/user-guides/system-overview are in sync (plan D29)
	$(COMPOSE) exec app php scripts/docs-check.php
