<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Xclass\Controller;

use Netlogix\Nxvarnish\Xclass\Controller\TypoScriptFrontendController;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class TypoScriptFrontendControllerTest extends UnitTestCase
{

    #[Test]
    public function itAllowsCachingIfNotDisabledInPage(): void
    {
        $subject = $this->getMockBuilder(TypoScriptFrontendController::class)
            ->disableOriginalConstructor()
            ->addMethods([])
            ->getMock();

        $subject->page = ['tx_nxvarnish_no_ext_cache' => false];

        self::assertTrue($subject->isStaticCacheble());
    }

    #[Test]
    public function itPreventsCachingIfDisabledInPage(): void
    {
        $subject = $this->getMockBuilder(TypoScriptFrontendController::class)
            ->disableOriginalConstructor()
            ->addMethods([])
            ->getMock();

        $subject->page = ['tx_nxvarnish_no_ext_cache' => true];

        self::assertFalse($subject->isStaticCacheble());
    }
}
