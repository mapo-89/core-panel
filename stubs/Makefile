# Makefile
.PHONY: help lint frontend-eslint frontend-prettier frontend-typecheck frontend-typecheck-incremental frontend-lint frontend-lint-incremental format test test-compact test-dirty test-feature analyze analyze-dirty build smoke qa qa-backend qa-frontend qa-ci

.DEFAULT_GOAL := help

PHP_PINT := php ./vendor/bin/pint
PHP_ARTISAN := php artisan
PHP_PEST := php ./vendor/bin/pest
PHP_STAN := php ./vendor/bin/phpstan

help: ## ✨ Show this help message
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

lint: ## 🔍 Check for code style issues
	@$(PHP_PINT) --test

frontend-eslint: ## 🔍 Run ESLint for frontend code
	@npm run lint:eslint

frontend-prettier: ## 🔍 Check frontend formatting with Prettier
	@npm run lint:prettier

frontend-typecheck: ## 🔍 Run TypeScript checks for frontend code
	@npm run typecheck

frontend-typecheck-incremental: ## 🔍 Run incremental TypeScript checks for frontend code
	@npm exec vue-tsc -- --noEmit --incremental --tsBuildInfoFile node_modules/.vue-tsc.tsbuildinfo -p tsconfig.json

frontend-lint: frontend-eslint frontend-prettier frontend-typecheck ## 🔍 Run frontend lint checks

frontend-lint-incremental: frontend-eslint frontend-prettier frontend-typecheck-incremental ## 🔍 Run frontend lint checks with incremental TypeScript checks

format-dirty: ## 🎨 Automatically format the code
	@$(PHP_PINT) --dirty

format: ## 🎨 Automatically format the code
	@$(PHP_PINT)

test: ## 🧪 Run the application test suite
	@$(PHP_ARTISAN) test

test-compact: ## 🧪 Run the compact application test suite
	@$(PHP_ARTISAN) test --compact

test-dirty: ## 🧪 Run uncommitted changed Pest tests only
	@$(PHP_PEST) --dirty --compact

test-feature: ## 🧪 Run feature tests only
	@$(PHP_ARTISAN) test tests/Feature --compact

analyze: ## 🔬 Run static analysis with PHPStan
	@$(PHP_STAN) analyse --memory-limit=1G -c phpstan.neon.dist

analyze-dirty: ## 🔬 Run PHPStan for uncommitted changed PHP files only
	@files="$$( { \
		git diff --name-only --diff-filter=ACMR HEAD -- app database routes tests; \
		git ls-files --others --exclude-standard -- app database routes tests; \
	} | grep '\.php$$' | sort -u || true)"; \
	if [ -z "$$files" ]; then \
		echo "No changed PHP files found."; \
	else \
		$(PHP_STAN) analyse --memory-limit=1G -c phpstan.neon.dist --debug --no-progress $$files; \
	fi

build: ## 🏗️ Build frontend assets
	npm ci
	npm run build

smoke: ## 🕵️ Run host smoke checks
	bash scripts/smoke.sh

qa: format-dirty frontend-lint-incremental analyze-dirty test-dirty ## 🚀 Run all code quality checks
	@echo "✅ All quality checks passed successfully!"

qa-backend: format-dirty analyze-dirty test-dirty ## 🚀 Run all backend quality checks

qa-frontend: frontend-lint build ## 🚀 Run all frontend quality checks

qa-ci: lint frontend-lint analyze build test-compact smoke ## 🚀 Run the full CI-like quality checks
	@echo "✅ All CI checks passed"
