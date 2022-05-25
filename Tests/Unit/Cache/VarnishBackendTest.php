<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Cache;

use Netlogix\Nxvarnish\Cache\Backend\VarnishBackend;
use Netlogix\Nxvarnish\Service\VarnishService;
use Nimut\TestingFramework\TestCase\UnitTestCase;

use function PHPUnit\Framework\at;

class VarnishBackendTest extends UnitTestCase
{

    /**
     * @var VarnishBackend
     */
    protected $subject;

    public function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->getMockBuilder(VarnishBackend::class)->disableOriginalConstructor()->onlyMethods(
            ['getVarnishService']
        )->getMock();

        // mock this the hard way until dependency injection can be used
        $varnishService = $this->createStub(VarnishService::class);

        $this->subject->expects(self::any())->method('getVarnishService')->willReturn($varnishService);
    }

    /**
     * @test
     * @return void
     */
    public function itReturnsFalseForIdentifier()
    {
        $res = $this->subject->get(uniqid());

        self::assertFalse($res);
    }

    /**
     * @test
     * @return void
     */
    public function itCanSetData()
    {
        $this->subject->set(uniqid(), uniqid());

        // an error would throw an exception
        self::assertTrue(true);
    }

    /**
     * @test
     * @return void
     */
    public function itDoesNotStoreAnyData()
    {
        $id = uniqid();
        $data = uniqid();
        $tags = [uniqid()];

        $this->subject->set($id, $data, $tags);

        // an error would throw an exception
        self::assertFalse($this->subject->get($id));
        self::assertFalse($this->subject->has($id));
        self::assertEmpty($this->subject->findIdentifiersByTag($tags[0]));
    }

    /**
     * @test
     * @return void
     */
    public function itReportsMissingEntryWhenRemoving()
    {
        self::assertFalse($this->subject->remove(uniqid()));
    }

    /**
     * @test
     * @return void
     */
    public function flushTriggersCompleteBan()
    {
        $varnishService = $this->getMockBuilder(VarnishService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $varnishService->expects(self::once())->method('banTag')->with('.*');

        $subject = $this->getMockBuilder(VarnishBackend::class)->disableOriginalConstructor()->onlyMethods(
            ['getVarnishService']
        )->getMock();

        $subject->expects(self::any())->method('getVarnishService')->willReturn($varnishService);
        $subject->flush();
    }

    /**
     * @test
     * @return void
     */
    public function flushByTagTriggersBanOfTag()
    {
        $tag = uniqid();

        $varnishService = $this->getMockBuilder(VarnishService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $varnishService->expects(self::once())->method('banTag')->with(';' . $tag . ';');

        $subject = $this->getMockBuilder(VarnishBackend::class)->disableOriginalConstructor()->onlyMethods(
            ['getVarnishService']
        )->getMock();
        $subject->expects(self::any())->method('getVarnishService')->willReturn($varnishService);
        $subject->flushByTag($tag);
    }

    /**
     * @test
     * @return void
     */
    public function flushByTagsTriggersMultipleBanOfTag()
    {
        $tag1 = uniqid();
        $tag2 = uniqid();

        $varnishService = $this->getMockBuilder(VarnishService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $varnishService->expects(at(0))->method('banTag')->with(';' . $tag1 . ';');
        $varnishService->expects(at(1))->method('banTag')->with(';' . $tag2 . ';');

        $subject = $this->getMockBuilder(VarnishBackend::class)->disableOriginalConstructor()->onlyMethods(
            ['getVarnishService']
        )->getMock();
        $subject->expects(self::any())->method('getVarnishService')->willReturn($varnishService);
        $subject->flushByTags([$tag1, $tag2]);
    }


    /**
     * @test
     * @return void
     */
    public function tagsIncludingTableNamesAreCompressed()
    {
        $table = uniqid();
        $id = rand(1, 9999);

        $varnishService = $this->getMockBuilder(VarnishService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $varnishService->expects(self::once())->method('banTag')->with(
            sprintf(';%s{[0-9,]*,%d,[0-9,]*};', $table, $id)
        );

        $subject = $this->getMockBuilder(VarnishBackend::class)->disableOriginalConstructor()->onlyMethods(
            ['getVarnishService']
        )->getMock();
        $subject->expects(self::any())->method('getVarnishService')->willReturn($varnishService);
        $subject->flushByTag(sprintf('%s_%d', $table, $id));
    }
}
