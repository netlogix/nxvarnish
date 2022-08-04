<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Hooks;

use Netlogix\Nxvarnish\Hooks\ContentPostProcHook;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ContentPostProcHookTest extends UnitTestCase
{

    /**
     * @test
     * @return void
     */
    public function itDoesNotAddHeadersIfNotBehindReverseProxy()
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

        $subject = new ContentPostProcHook();
        $subject->cached([], $mockTsfe);

        self::assertEmpty($mockTsfe->config['config']['additionalHeaders.'] ?? []);
    }

    /**
     * @test
     * @return void
     */
    public function itAddsHeadersIfBehindReverseProxy()
    {
        $paramsMock = $this->getMockBuilder(NormalizedParams::class)->disableOriginalConstructor()->getMock();
        $paramsMock->expects(self::any())->method('isBehindReverseProxy')->willReturn(true);

        $GLOBALS['TYPO3_REQUEST'] = $this->getMockBuilder(ServerRequest::class)->disableOriginalConstructor()->getMock(
        );

        $GLOBALS['TYPO3_REQUEST']->expects(self::any())->method('getAttribute')->with('normalizedParams')->willReturn(
            $paramsMock
        );

        $mockTsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $mockTsfe->expects(self::once())->method('getPageCacheTags')->willReturn([uniqid() . '_' . rand(1, 1000)]);

        $subject = new ContentPostProcHook();
        $subject->cached([], $mockTsfe);

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

    /**
     * @test
     * @return void
     */
    public function itAddsPageCacheTagAutomatically()
    {
        $paramsMock = $this->getMockBuilder(NormalizedParams::class)->disableOriginalConstructor()->getMock();
        $paramsMock->expects(self::any())->method('isBehindReverseProxy')->willReturn(true);

        $GLOBALS['TYPO3_REQUEST'] = $this->getMockBuilder(ServerRequest::class)->disableOriginalConstructor()->getMock(
        );

        $GLOBALS['TYPO3_REQUEST']->expects(self::any())->method('getAttribute')->with('normalizedParams')->willReturn(
            $paramsMock
        );

        $mockTsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $mockTsfe->id = 1;
        $mockTsfe->expects(self::once())->method('getPageCacheTags')->willReturn(['sys_file_1', 'sys_file_metadata_1']);

        $subject = new ContentPostProcHook();
        $subject->cached([], $mockTsfe);

        $header = $mockTsfe->config['config']['additionalHeaders.'][0]['header'];

        self::assertEquals('X-Cache-Tags: ;sys_file{,1,};sys_file_metadata{,1,};pageId{,1,};', $header);
    }

    /**
     * @test
     * @return void
     */
    public function itGroupsCacheTagHeaders()
    {
        $paramsMock = $this->getMockBuilder(NormalizedParams::class)->disableOriginalConstructor()->getMock();
        $paramsMock->expects(self::any())->method('isBehindReverseProxy')->willReturn(true);

        $GLOBALS['TYPO3_REQUEST'] = $this->getMockBuilder(ServerRequest::class)->disableOriginalConstructor()->getMock(
        );

        $GLOBALS['TYPO3_REQUEST']->expects(self::any())->method('getAttribute')->with('normalizedParams')->willReturn(
            $paramsMock
        );

        $mockTsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $mockTsfe->id = 1;
        $mockTsfe->expects(self::once())->method('getPageCacheTags')->willReturn(['sys_file_1', 'sys_file_2', 'sys_file_metadata_1']);

        $subject = new ContentPostProcHook();
        $subject->cached([], $mockTsfe);

        $header = $mockTsfe->config['config']['additionalHeaders.'][0]['header'];

        self::assertEquals('X-Cache-Tags: ;sys_file{,1,2,};sys_file_metadata{,1,};pageId{,1,};', $header);
    }

    /**
     * @test
     * @return void
     */
    public function itRemovesIndividualIdsWhenTableIsTagged()
    {
        $paramsMock = $this->getMockBuilder(NormalizedParams::class)->disableOriginalConstructor()->getMock();
        $paramsMock->expects(self::any())->method('isBehindReverseProxy')->willReturn(true);

        $GLOBALS['TYPO3_REQUEST'] = $this->getMockBuilder(ServerRequest::class)->disableOriginalConstructor()->getMock(
        );

        $GLOBALS['TYPO3_REQUEST']->expects(self::any())->method('getAttribute')->with('normalizedParams')->willReturn(
            $paramsMock
        );

        $GLOBALS['TCA']['sys_file'] = [];

        $mockTsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $mockTsfe->id = 1;
        $mockTsfe->expects(self::once())->method('getPageCacheTags')->willReturn(['sys_file_1', 'sys_file_2', 'sys_file', 'sys_file_metadata_1']);

        $subject = new ContentPostProcHook();
        $subject->cached([], $mockTsfe);

        $header = $mockTsfe->config['config']['additionalHeaders.'][0]['header'];

        self::assertEquals('X-Cache-Tags: ;sys_file;sys_file_metadata{,1,};pageId{,1,};', $header);
    }
}
