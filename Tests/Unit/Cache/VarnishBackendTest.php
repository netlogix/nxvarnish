<?php

declare(strict_types=1);

namespace Netlogix\Nxvarnish\Tests\Unit\Cache;

use Override;
use InvalidArgumentException;
use Netlogix\Nxvarnish\Cache\Backend\VarnishBackend;
use Netlogix\Nxvarnish\Service\VarnishService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class VarnishBackendTest extends UnitTestCase
{
    protected VarnishBackend $subject;

    protected bool $resetSingletonInstances = true;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new VarnishBackend('test');
    }

    #[Test]
    public function itReturnsFalseForIdentifier(): void
    {
        $res = $this->subject->get(uniqid());

        $this->assertFalse($res);
    }

    #[Test]
    public function itCanSetData(): void
    {
        $this->subject->set(uniqid(), uniqid());

        // an error would throw an exception
        $this->assertTrue(true);
    }

    #[Test]
    public function itDoesNotStoreAnyData(): void
    {
        $id = uniqid();
        $data = uniqid();
        $tags = [uniqid()];

        $this->subject->set($id, $data, $tags);

        // an error would throw an exception
        $this->assertFalse($this->subject->get($id));
        $this->assertFalse($this->subject->has($id));
        $this->assertEmpty($this->subject->findIdentifiersByTag($tags[0]));
    }

    #[Test]
    public function itReportsMissingEntryWhenRemoving(): void
    {
        $this->assertFalse($this->subject->remove(uniqid()));
    }

    #[Test]
    public function flushTriggersCompleteBan(): void
    {
        $varnishServiceMock = $this->getVarnishServiceMock();
        $varnishServiceMock->expects($this->once())->method('banTag')->with('.*');
        $this->subject->flush();
    }

    #[Test]
    public function flushByTagTriggersBanOfTag(): void
    {
        $tag = uniqid();
        $varnishServiceMock = $this->getVarnishServiceMock();
        $varnishServiceMock
            ->expects($this->once())
            ->method('banTag')
            ->with(';' . $tag . ';');
        $this->subject->flushByTag($tag);
    }

    #[Test]
    public function flushByTagsTriggersMultipleBanOfTag(): void
    {
        $tag1 = uniqid();
        $tag2 = uniqid();
        $varnishServiceMock = $this->getVarnishServiceMock();
        $varnishServiceMock
            ->expects($this->exactly(2))
            ->method('banTag')
            ->with(...$this->consecutiveParams([';' . $tag1 . ';'], [';' . $tag2 . ';']));
        $this->subject->flushByTags([$tag1, $tag2]);
    }

    #[Test]
    public function tagsIncludingTableNamesAreCompressed(): void
    {
        $table = uniqid();
        $id = random_int(1, 9999);

        $varnishServiceMock = $this->getVarnishServiceMock();
        $varnishServiceMock
            ->expects($this->once())
            ->method('banTag')
            ->with(sprintf(';%s{[0-9,]*,%d,[0-9,]*};', $table, $id));
        $this->subject->flushByTag(sprintf('%s_%d', $table, $id));
    }

    private function getVarnishServiceMock(): VarnishService
    {
        // mock this the hard way until dependency injection can be used
        $varnishServiceMock = $this->getMockBuilder(VarnishService::class)
            ->disableOriginalConstructor()
            ->getMock();
        GeneralUtility::setSingletonInstance(VarnishService::class, $varnishServiceMock);
        return $varnishServiceMock;
    }

    // @see: https://gist.github.com/ziadoz/370fe63e24f31fd1eb989e7477b9a472
    public function consecutiveParams(array ...$args): array
    {
        $callbacks = [];
        $count = count(max($args));

        for ($index = 0; $index < $count; $index++) {
            $returns = [];

            foreach ($args as $arg) {
                if (!array_is_list($arg)) {
                    throw new InvalidArgumentException('Every array must be a list');
                }

                if (!isset($arg[$index])) {
                    throw new InvalidArgumentException(sprintf('Every array must contain %d parameters', $count));
                }

                $returns[] = $arg[$index];
            }

            $callbacks[] = $this->callback(
                new class ($returns) {
                    public function __construct(protected array $returns) {}

                    public function __invoke(mixed $actual): bool
                    {
                        if ($this->returns === []) {
                            return true;
                        }

                        return $actual === array_shift($this->returns);
                    }
                },
            );
        }

        return $callbacks;
    }
}
