<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Service;

use Netlogix\Nxvarnish\Service\ExposeService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ExposeServiceTest extends UnitTestCase
{
    #[Test]
    public function itAddsPageCacheTagAutomatically(): void
    {
        $mockTsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $mockTsfe->id = 1;
        $mockTsfe->expects(self::once())->method('getPageCacheTags')->willReturn(['sys_file_1', 'sys_file_metadata_1']);

        $subject = new ExposeService();
        $cacheTags = $subject->getPageCacheTags($mockTsfe);

        self::assertEquals(['pageId{,1,}', 'sys_file_metadata{,1,}', 'sys_file{,1,}'], $cacheTags);
    }

    #[Test]
    public function itGroupsCacheTagHeaders(): void
    {
        $mockTsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $mockTsfe->id = 1;
        $mockTsfe->expects(self::once())->method('getPageCacheTags')->willReturn(['sys_file_1', 'sys_file_2', 'sys_file_metadata_1']);

        $subject = new ExposeService();
        $cacheTags = $subject->getPageCacheTags($mockTsfe);

        self::assertEquals(['pageId{,1,}', 'sys_file_metadata{,1,}', 'sys_file{,1,2,}'], $cacheTags);
    }

    #[Test]
    public function itRemovesIndividualIdsWhenTableIsTagged(): void
    {
        $GLOBALS['TCA']['sys_file'] = [];

        $mockTsfe = $this->getMockBuilder(TypoScriptFrontendController::class)->disableOriginalConstructor()->getMock();
        $mockTsfe->id = 1;
        $mockTsfe->expects(self::once())->method('getPageCacheTags')->willReturn(['sys_file_1', 'sys_file_2', 'sys_file', 'sys_file_metadata_1']);

        $subject = new ExposeService();
        $cacheTags = $subject->getPageCacheTags($mockTsfe);

        self::assertEquals(['pageId{,1,}', 'sys_file', 'sys_file_metadata{,1,}'], $cacheTags);
    }
}
