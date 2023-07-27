.PHONY: clean deps test

clean:
	rm -rf .Build/

deps:
	composer install

update:
	composer update -W

test:
	XDEBUG_MODE=coverage .Build/bin/phpunit -c phpunit.xml
	XDEBUG_MODE=coverage .Build/bin/phpunit -c phpunit_functional.xml
	# merge into php coverage
	.Build/bin/phpcov merge --php .Build/logs/coverage.php --html .Build/logs/coverage/merged --cobertura .Build/logs/cobertura.xml .Build/logs/coverage/

ci:
	act --platform ubuntu-22.04=shivammathur/node:2204
