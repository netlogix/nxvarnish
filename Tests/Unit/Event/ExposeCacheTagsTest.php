<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Event;

use Netlogix\Nxvarnish\Event\ExposeCacheTags;
use Netlogix\Nxvarnish\Hooks\ContentPostProcHook;
use Netlogix\Nxvarnish\Service\ExposeService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;


class ExposeCacheTagsTest extends UnitTestCase
{

    #[Test]
    public function itDoesNotAddHeadersIfNotBehindReverseProxy(): void
    {
        $paramsMock = $this->getMockBuilder(NormalizedParams::class)->disableOriginalConstructor()->getMock();
        $paramsMock->expects(self::any())->method('isBehindReverseProxy')->willReturn(false);

        $GLOBALS['TYPO3_REQUEST'] = $this->getMockBuilder(ServerRequest::class)->disableOriginalConstructor()->getMock(
        );

        $GLOBALS['TYPO3_REQUEST']->expects(self::any())->method('getAttribute')->with('normalizedParams')->willReturn(
            $paramsMock
        );

        $mockTsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $mockTsfe->addCacheTags([uniqid() . '_' . rand(1, 1000)]);

        $requestMock = $this->getMockBuilder(ServerRequest::class)->disableOriginalConstructor()->getMock();
        $requestMock->method('getAttribute')->with('normalizedParams')->willReturn($paramsMock);
        $requestMock->method('hasHeader')->with('X-Esi')->willReturn(false);


        $event = new AfterCacheableContentIsGeneratedEvent($requestMock, $mockTsfe, uniqid(), false);

        $subject = new ExposeCacheTags();
        $subject ->__invoke($event);

        self::assertEmpty($mockTsfe->config['config']['additionalHeaders.'] ?? []);
    }

    #[Test]
    public function itAddsHeadersIfBehindReverseProxy(): void
    {
        // Mock the dependencies
        $paramsMock = $this->getMockBuilder(NormalizedParams::class)->disableOriginalConstructor()->getMock();
        $paramsMock->method('isBehindReverseProxy')->willReturn(true);

        $GLOBALS['TYPO3_REQUEST'] = $this->getMockBuilder(ServerRequest::class)->disableOriginalConstructor()->getMock(
        );

        $GLOBALS['TYPO3_REQUEST']->expects(self::any())->method('getAttribute')->with('normalizedParams')->willReturn(
            $paramsMock
        );

        $requestMock = $this->getMockBuilder(ServerRequest::class)->disableOriginalConstructor()->getMock();
        $requestMock->method('getAttribute')->with('normalizedParams')->willReturn($paramsMock);
        $requestMock->method('hasHeader')->with('X-Esi')->willReturn(false);

        $mockTsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $mockTsfe->id = 1;
        $mockTsfe->expects(self::once())->method('getPageCacheTags')->willReturn([uniqid() . '_' . rand(1, 1000)]);

        $event = new AfterCacheableContentIsGeneratedEvent($requestMock, $mockTsfe, uniqid(), false);

        $subject = new ExposeCacheTags();
        $subject ->__invoke($event);

        self::assertNotEmpty($mockTsfe->config['config']['additionalHeaders.']);

        $headersFound = false;

        foreach ($mockTsfe->config['config']['additionalHeaders.'] as $additionalHeader) {
            if (isset($additionalHeader['header']) && stripos($additionalHeader['header'], 'X-Cache-Tags:') === 0) {
                $headersFound = true;
                break;
            }
        }

        self::assertTrue($headersFound, 'failed to find expected header "X-Cache-Tags"');
    }
}
