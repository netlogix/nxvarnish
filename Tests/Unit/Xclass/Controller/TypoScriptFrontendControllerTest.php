<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Xclass\Controller;

use Netlogix\Nxvarnish\Xclass\Controller\TypoScriptFrontendController;
use Nimut\TestingFramework\TestCase\UnitTestCase;

class TypoScriptFrontendControllerTest extends UnitTestCase
{

    /**
     * @test
     * @return void
     */
    public function itAllowsCachingIfNotDisabledInPage()
    {
        $subject = $this->getMockBuilder(TypoScriptFrontendController::class)
            ->disableOriginalConstructor()
            ->addMethods([])
            ->getMock();

        $subject->page = ['tx_nxvarnish_no_ext_cache' => false];

        self::assertTrue($subject->isStaticCacheble());
    }

    /**
     * @test
     * @return void
     */
    public function itPreventsCachingIfDisabledInPage()
    {
        $subject = $this->getMockBuilder(TypoScriptFrontendController::class)
            ->disableOriginalConstructor()
            ->addMethods([])
            ->getMock();

        $subject->page = ['tx_nxvarnish_no_ext_cache' => true];

        self::assertFalse($subject->isStaticCacheble());
    }
}
