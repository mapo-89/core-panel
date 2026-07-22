# Makefile
.PHONY: help lint format test test-compact test-feature analyze build smoke qa qa-ci

.DEFAULT_GOAL := help

PHP_PINT := ./vendor/bin/pint
PHP_ARTISAN := php artisan
PHP_STAN := ./vendor/bin/phpstan

help: ## ✨ Show this help message
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

lint: ## 🔍 Check for code style issues
	@$(PHP_PINT) --test

format-dirty: ## 🎨 Automatically format the code
	@$(PHP_PINT) --dirty

format: ## 🎨 Automatically format the code
	@$(PHP_PINT)

test: ## 🧪 Run the application test suite
	@$(PHP_ARTISAN) test

test-compact: ## 🧪 Run the compact application test suite
	@$(PHP_ARTISAN) test --compact

test-feature: ## 🧪 Run feature tests only
	@$(PHP_ARTISAN) test tests/Feature --compact

analyze: ## 🔬 Run static analysis with PHPStan
	@$(PHP_STAN) analyse --memory-limit=1G -c phpstan.neon.dist

build: ## 🏗️ Build frontend assets
	npm ci
	npm run build

smoke: ## 🕵️ Run host smoke checks
	bash scripts/smoke.sh

qa: format-dirty analyze test-compact ## 🚀 Run all code quality checks
	@echo "✅ All quality checks passed successfully!"

qa-ci: lint analyze test-compact build smoke ## 🚀 Run the full CI-like quality checks
	@echo "✅ All CI checks passed"
