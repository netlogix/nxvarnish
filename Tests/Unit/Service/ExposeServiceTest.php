<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Hooks;

use Netlogix\Nxvarnish\Hooks\ContentPostProcHook;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ExposeServiceTest extends UnitTestCase
{
    #[Test]
    public function itAddsPageCacheTagAutomatically(): void
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

    #[Test]
    public function itGroupsCacheTagHeaders(): void
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

    #[Test]
    public function itRemovesIndividualIdsWhenTableIsTagged(): void
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
