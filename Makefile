help:
	@echo "Please use \`make <target>' where <target> is one of"
	@echo "  test                           to perform unit tests"
	@echo "  coverage                       to perform unit tests with code coverage"
	@echo "  coverage-show                  to show the code coverage report"
	@echo "  static                         to run phpstan and php-cs-fixer on the codebase"
	@echo "  static-phpstan                 to run phpstan on the codebase"
	@echo "  static-phpstan-update-baseline to regenerate the phpstan baseline file"
	@echo "  static-codestyle-fix           to run php-cs-fixer on the codebase, writing the changes"
	@echo "  static-codestyle-check         to run php-cs-fixer on the codebase"
	@echo "  static-psalm-generate-baseline to generate the psalm baseline"

test:
	vendor/bin/phpunit

coverage:
	vendor/bin/phpunit --coverage-html=build/artifacts/coverage

coverage-show:
	open build/artifacts/coverage/index.html

static: static-phpstan static-codestyle-check

static-phpstan:
	docker run --rm -it -e REQUIRE_DEV=true -v ${PWD}:/app -w /app oskarstark/phpstan-ga:1.4.6 analyze $(PHPSTAN_PARAMS)

static-phpstan-update-baseline:
	$(MAKE) static-phpstan PHPSTAN_PARAMS="--generate-baseline"

static-codestyle-fix:
	docker run --rm -it -v ${PWD}:/app -w /app oskarstark/php-cs-fixer-ga:2.16.4 --diff-format udiff $(CS_PARAMS)

static-codestyle-check:
	$(MAKE) static-codestyle-fix CS_PARAMS="--dry-run"
