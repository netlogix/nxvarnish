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
	.Build/bin/phpcov merge --html .Build/artifacts/coverage/merged --clover .Build/artifacts/coverage/clover.xml .Build/artifacts/coverage/
