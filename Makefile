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
	# merge and generate clover and html report
	XDEBUG_MODE=coverage .Build/bin/phpunit-merger coverage .Build/logs/coverage/ --html=.Build/logs/html/ .Build/logs/clover.xml
	# merge into php coverage
	.Build/bin/phpcov merge --php .Build/logs/coverage.php .Build/logs/coverage/
	# this is currently buggy and will merge into empty results
	.Build/bin/phpunit-merger log .Build/logs/junit/ .Build/logs/junit.xml

ci:
	act --platform ubuntu-20.04=shivammathur/node:2004
