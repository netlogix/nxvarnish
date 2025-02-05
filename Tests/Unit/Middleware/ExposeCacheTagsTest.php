<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Middleware;

use Netlogix\Nxvarnish\Middleware\ExposeCacheTags;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ExposeCacheTagsTest extends UnitTestCase
{
    protected ExposeCacheTags $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ExposeCacheTags();
    }

    #[Test]
    public function doNotExposeCacheTagsWhenNotBehindReverseProxy(): void
    {
        $request = $this->getRequest(isBehindReverseProxy: false);

        $response = new Response();
        $requestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $requestHandler->method('handle')->willReturn($response);

        $response = $this->subject->process($request, $requestHandler);
        $this->assertEmpty($response->getHeaderLine('X-Cache-Tags'));
    }

    #[Test]
    public function exposeCacheTags(): void
    {
        $request = $this->getRequest();
        $response = new Response();
        $requestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $requestHandler->method('handle')->willReturn($response);
        $request = $request->withAttribute('frontend.cache.collector', $this->getCacheDataCollector());

        $response = $this->subject->process($request, $requestHandler);
        $this->assertSame(
            'tx_nxvarnish_testtest;tx_nxvarnish_testtesttest;tx_nxvarnish_testtesttest{,2,};tx_nxvarnish_test{,1,3,2,};',
            $response->getHeaderLine('X-Cache-Tags'),
        );
    }

    #[Test]
    public function exposeCacheTagsInVarnishRequest(): void
    {
        $request = $this->getRequest(isBehindReverseProxy: false);
        $request = $request->withHeader('x-varnish', '1');

        $response = new Response();
        $requestHandler = $this->getMockBuilder(RequestHandlerInterface::class)->getMock();
        $requestHandler->method('handle')->willReturn($response);
        $cacheDataCollector = new CacheDataCollector();
        $cacheDataCollector->addCacheTags(new CacheTag('pageId_1'));

        $request = $request->withAttribute('frontend.cache.collector', $cacheDataCollector);

        $response = $this->subject->process($request, $requestHandler);
        $this->assertSame('pageId{,1,};', $response->getHeaderLine('X-Cache-Tags'));
    }

    private function getRequest($isBehindReverseProxy = true): ServerRequestInterface
    {
        $normalizedParams = $this->getMockBuilder(NormalizedParams::class)
            ->disableOriginalConstructor()
            ->getMock();
        $normalizedParams->method('isBehindReverseProxy')->willReturn($isBehindReverseProxy);
        return (new ServerRequest())->withAttribute('normalizedParams', $normalizedParams);
    }

    private function getCacheDataCollector(): CacheDataCollector
    {
        $cacheDataCollector = new CacheDataCollector();
        $cacheDataCollector->addCacheTags(
            new CacheTag('tx_nxvarnish_test_1'),
            new CacheTag('tx_nxvarnish_test_3'),
            new CacheTag('tx_nxvarnish_test_2'),
            new CacheTag('tx_nxvarnish_testtest_4'),
            new CacheTag('tx_nxvarnish_testtest_3'),
            new CacheTag('tx_nxvarnish_testtest_2'),
            new CacheTag('tx_nxvarnish_testtest'),
            new CacheTag('tx_nxvarnish_testtesttest'),
            new CacheTag('tx_nxvarnish_testtesttest_2'),
        );

        $GLOBALS['TCA'] = [
            'tx_nxvarnish_test' => [],
            'tx_nxvarnish_testtest' => [],
        ];

        return $cacheDataCollector;
    }
}
