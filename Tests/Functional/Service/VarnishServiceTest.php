<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Functional\Service;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Netlogix\Nxvarnish\Service\VarnishService;
use Psr\Log\LoggerInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Psr\Http\Message\RequestInterface;
use PHPUnit\Framework\Attributes\Test;

class VarnishServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3conf/ext/nxvarnish'];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'nxvarnish' => [
                'varnishHost' => 'http://varnish.example.com:8080',
            ],
        ]
    ];

    #[Test]
    public function banTagCreatesRequestUsingBanMethod(): void
    {
        $tag = uniqid();

        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use ($tag) {
                if ($request->getMethod() != 'BAN') {
                    self::fail('Expected Request to use method "BAN"');
                }

                return new Response();
            }
        ]);
        $stack = HandlerStack::create($mock);

        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] = $stack;

        $subject = new VarnishService();

        $subject->banTag($tag);

        self::assertTrue(true);
    }

    #[Test]
    public function banTagCreatesRequestIncludingVarnishHeader(): void
    {
        $tag = uniqid();

        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use ($tag) {
                if (!$request->hasHeader('X-Cache-Tags')) {
                    self::fail('Expected Request to use header "X-Cache-Tags"');
                }

                if (stripos($request->getHeaderLine('X-Cache-Tags'), $tag) === false) {
                    self::fail('Expected Request to use header "X-Cache-Tags" containing tag');
                }

                return new Response();
            }
        ]);
        $stack = HandlerStack::create($mock);

        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] = $stack;

        $subject = new VarnishService();

        $subject->banTag($tag);

        self::assertTrue(true);
    }

    #[Test]
    public function guzzleErrorsAreCachedAndLogged(): void
    {
        $tag = uniqid();

        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use ($tag) {
                if ($request->getMethod() != 'BAN') {
                    self::fail('Expected Request to use method "BAN"');
                }

                throw new InvalidArgumentException('Test');
            }
        ]);
        $stack = HandlerStack::create($mock);

        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] = $stack;

        $subject = new VarnishService();

        $loggerMock = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $loggerMock->expects(self::once())->method('error');

        $subject->setLogger($loggerMock);
        $subject->banTag($tag);
    }

    private function getLoggerMock()
    {
    }

}
