<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Functional\Service;

use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Netlogix\Nxvarnish\Service\VarnishService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class VarnishServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['typo3conf/ext/nxvarnish'];

    #[Test]
    public function banTagCreatesRequestUsingBanMethod(): void
    {
        $tag = uniqid();

        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use ($tag): Response {
                if ($request->getMethod() !== 'BAN') {
                    self::fail('Expected Request to use method "BAN"');
                }

                return new Response();
            },
        ]);
        $stack = HandlerStack::create($mock);

        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] = $stack;

        $subject = new VarnishService(
            $this->getRequestFactory(),
            $this->getLogger(),
            'http://varnish.example.com:8080'
        );
        $subject->banTag($tag);

        $this->assertTrue(true);
    }

    #[Test]
    public function banTagCreatesRequestIncludingVarnishHeader(): void
    {
        $tag = uniqid();

        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use ($tag): Response {
                if (!$request->hasHeader('X-Cache-Tags')) {
                    self::fail('Expected Request to use header "X-Cache-Tags"');
                }

                if (stripos($request->getHeaderLine('X-Cache-Tags'), $tag) === false) {
                    self::fail('Expected Request to use header "X-Cache-Tags" containing tag');
                }

                return new Response();
            },
        ]);
        $stack = HandlerStack::create($mock);

        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] = $stack;

        $subject = new VarnishService(
            $this->getRequestFactory(),
            $this->getLogger(),
            'http://varnish.example.com:8080'
        );
        $subject->banTag($tag);

        $this->assertTrue(true);
    }

    #[Test]
    public function guzzleErrorsAreCachedAndLogged(): void
    {
        $tag = uniqid();

        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use ($tag): void {
                if ($request->getMethod() !== 'BAN') {
                    self::fail('Expected Request to use method "BAN"');
                }

                throw new InvalidArgumentException('Test');
            },
        ]);
        $stack = HandlerStack::create($mock);

        $GLOBALS['TYPO3_CONF_VARS']['HTTP']['handler'] = $stack;

        $loggerMock = $this->getLogger();
        $loggerMock->expects($this->once())->method('error');

        $subject = new VarnishService($this->getRequestFactory(), $loggerMock, 'http://varnish.example.com:8080');
        $subject->banTag($tag);
    }

    private function getLogger(): MockObject
    {
        return $this->getMockBuilder(LoggerInterface::class)->getMock();
    }

    private function getRequestFactory(): RequestFactory
    {
        return GeneralUtility::makeInstance(RequestFactory::class);
    }
}
