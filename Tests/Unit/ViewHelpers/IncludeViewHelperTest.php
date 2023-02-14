<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\ViewHelpers;

use Netlogix\Nxvarnish\ViewHelpers\IncludeViewHelper;
use Nimut\TestingFramework\TestCase\ViewHelperBaseTestcase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

class IncludeViewHelperTest extends ViewHelperBaseTestcase
{

    /**
     * @var MockObject|IncludeViewHelper
     */
    protected $viewHelper;

    /**
     * @test
     * @return void
     */
    public function itRendersEsiTagForSource()
    {
        $source = sprintf('http://www.example.com/%s', uniqid());

        $this->arguments = ['src' => $source];

        $this->injectDependenciesIntoViewHelper($this->viewHelper);
        $this->viewHelper->initializeArguments();


        $res = $this->viewHelper->render();

        self::assertEquals(sprintf('<esi:include src="%s"></esi:include>', $source), $res);
    }

    /**
     * @test
     * @return void
     */
    public function itReplacesHttpsSourcesWithHttp()
    {
        $source = sprintf('www.example.com/%s', uniqid());

        $this->arguments = ['src' => 'https://' . $source];

        $this->injectDependenciesIntoViewHelper($this->viewHelper);
        $this->viewHelper->initializeArguments();


        $res = $this->viewHelper->render();


        self::assertEquals(sprintf('<esi:include src="http://%s"></esi:include>', $source), $res);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewHelper = $this->getMockBuilder(IncludeViewHelper::class)->setMethods(['renderChildren'])->getMock();
        $this->viewHelper->setLogger(new NullLogger());
    }
}

