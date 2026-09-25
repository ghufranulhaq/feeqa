.DEFAULT_GOAL := help
.PHONY: help up down restart logs dev install key migrate fresh test test-filter \
        test-browser ci lint pint stan hooks demo-reset docs-check

COMPOSE := docker compose

# Overrides for anything that must differ from .env during tests. Exported
# inline rather than left to phpunit.xml's <env force="true">: PHPUnit's
# env-forcing only touches getenv()/putenv()/$_ENV, never $_SERVER, and a
# stale $_SERVER value (from the real process environment) wins otherwise.
TEST_ENV := APP_ENV=testing DB_DATABASE=feeqa_test CACHE_STORE=array \
            SESSION_DRIVER=array QUEUE_CONNECTION=sync MAIL_MAILER=array \
            PASSWORD_CHECK_BREACHED=false

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*## "}{printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

up: ## Start Postgres + Mailpit (Docker) — run `make dev` for the app/Vite
	$(COMPOSE) up -d postgres mailpit

down: ## Stop Postgres + Mailpit
	$(COMPOSE) stop postgres mailpit

restart: ## Restart Postgres + Mailpit
	$(COMPOSE) restart postgres mailpit

logs: ## Follow Postgres/Mailpit container logs (or: make logs S=postgres)
	$(COMPOSE) logs -f $(S)

dev: ## Run the app + queue listener + logs + Vite natively (Ctrl+C stops all)
	composer run dev

install: ## Install PHP and JS dependencies on the host
	composer install
	npm install
	php artisan storage:link

key: ## Generate the application key
	php artisan key:generate

migrate: ## Run database migrations
	php artisan migrate

fresh: ## Drop all tables and re-run migrations (local only — asks to confirm)
	php artisan migrate:fresh

test: ## Run the Pest test suite
	$(TEST_ENV) ./vendor/bin/pest

test-filter: ## Run tests matching a filter: make test-filter F=SomeTest
	$(TEST_ENV) ./vendor/bin/pest --filter=$(F)

test-browser: ## Run Playwright-backed browser/accessibility tests
	$(TEST_ENV) ./vendor/bin/pest --group=browser

ci: lint stan test docs-check ## Everything the pre-push hook runs

lint: ## Pint (PHP) + ESLint/Prettier (JS/TS) + tsc
	./vendor/bin/pint --test
	npm run lint
	npx tsc --noEmit

pint: ## Auto-fix PHP formatting
	./vendor/bin/pint

stan: ## Static analysis (Larastan)
	./vendor/bin/phpstan analyse

hooks: ## Enable the pre-push git hook (runs `make ci` before every push)
	git config core.hooksPath .githooks
	chmod +x .githooks/pre-push

demo-reset: ## Re-run migrations with base + demo seeders (asks to confirm)
	php artisan migrate:fresh --seed --seeder=Database\\Seeders\\DemoDatabaseSeeder

docs-check: ## Verify README/user-guides/system-overview are in sync (plan D29)
	php scripts/docs-check.php
