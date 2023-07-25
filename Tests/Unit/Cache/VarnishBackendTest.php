<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Cache;

use Netlogix\Nxvarnish\Cache\Backend\VarnishBackend;
use Netlogix\Nxvarnish\Service\VarnishService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

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

    #[Test]
    public function itReturnsFalseForIdentifier(): void
    {
        $res = $this->subject->get(uniqid());

        self::assertFalse($res);
    }

    #[Test]
    public function itCanSetData(): void
    {
        $this->subject->set(uniqid(), uniqid());

        // an error would throw an exception
        self::assertTrue(true);
    }

    #[Test]
    public function itDoesNotStoreAnyData(): void
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

    #[Test]
    public function itReportsMissingEntryWhenRemoving(): void
    {
        self::assertFalse($this->subject->remove(uniqid()));
    }

    #[Test]
    public function flushTriggersCompleteBan(): void
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

    #[Test]
    public function flushByTagTriggersBanOfTag(): void
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

    #[Test]
    public function flushByTagsTriggersMultipleBanOfTag(): void
    {
        $tag1 = uniqid();
        $tag2 = uniqid();

        $varnishService = $this->getMockBuilder(VarnishService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $varnishService->expects($this->exactly(2))->method('banTag')
            ->with(...$this->consecutiveParams(
                [';' . $tag1 . ';'],
                [';' . $tag2 . ';']
            ));

        $subject = $this->getMockBuilder(VarnishBackend::class)->disableOriginalConstructor()->onlyMethods(
            ['getVarnishService']
        )->getMock();
        $subject->expects(self::any())->method('getVarnishService')->willReturn($varnishService);
        $subject->flushByTags([$tag1, $tag2]);
    }


    #[Test]
    public function tagsIncludingTableNamesAreCompressed(): void
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




    // @see: https://gist.github.com/ziadoz/370fe63e24f31fd1eb989e7477b9a472
    public function consecutiveParams(array ...$args): array
    {
        $callbacks = [];
        $count = count(max($args));

        for ($index = 0; $index < $count; $index++) {
            $returns = [];

            foreach ($args as $arg) {
                if (! array_is_list($arg)) {
                    throw new \InvalidArgumentException('Every array must be a list');
                }

                if (! isset($arg[$index])) {
                    throw new \InvalidArgumentException(sprintf('Every array must contain %d parameters', $count));
                }

                $returns[] = $arg[$index];
            }

            $callbacks[] = $this->callback(new class ($returns) {
                public function __construct(protected array $returns)
                {
                }

                public function __invoke(mixed $actual): bool
                {
                    if (count($this->returns) === 0) {
                        return true;
                    }

                    return $actual === array_shift($this->returns);
                }
            });
        }

        return $callbacks;
    }



}
