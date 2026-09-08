<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Block\Adminhtml\Listing;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Ordo\Automation\Block\Adminhtml\Listing\AbstractEmptyStateHint;
use PHPUnit\Framework\TestCase;

/**
 * Exercised through a minimal concrete subclass (the class under test is abstract) - same
 * ObjectManager-singleton-stubbing technique BulkActionsTest already uses for the equivalent
 * Backend\Block\Template problem, needed here too since Context is stubbed rather than built for
 * real.
 */
class AbstractEmptyStateHintTest extends TestCase
{
    private UrlInterface $urlBuilder;

    protected function setUp(): void
    {
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($this->createStub(\stdClass::class));
        ObjectManager::setInstance($objectManager);

        $this->urlBuilder = $this->createStub(UrlInterface::class);
    }

    protected function tearDown(): void
    {
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));
    }

    private function makeBlock(int $collectionSize): AbstractEmptyStateHint
    {
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilder);

        return new class ($context, $collectionSize) extends AbstractEmptyStateHint {
            public function __construct(Context $context, private readonly int $size)
            {
                parent::__construct($context);
            }

            protected function getCollectionSize(): int
            {
                return $this->size;
            }
        };
    }

    public function testIsEmptyStateIsTrueWhenTheCollectionHasZeroRows(): void
    {
        self::assertTrue($this->makeBlock(0)->isEmptyState());
    }

    public function testIsEmptyStateIsFalseWhenTheCollectionHasRows(): void
    {
        self::assertFalse($this->makeBlock(3)->isEmptyState());
    }

    public function testIsEmptyStateOnlyQueriesTheCollectionOnce(): void
    {
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilder);

        $block = new class ($context) extends AbstractEmptyStateHint {
            public int $calls = 0;

            protected function getCollectionSize(): int
            {
                $this->calls++;
                return 0;
            }
        };

        $block->isEmptyState();
        $block->isEmptyState();

        self::assertSame(1, $block->calls);
    }

    public function testGetHintTextReturnsTheDataValue(): void
    {
        $block = $this->makeBlock(0);
        $block->setData('hint_text', 'No campaigns yet.');

        self::assertSame('No campaigns yet.', $block->getHintText());
    }

    public function testGetHintTextReturnsEmptyStringWhenDataIsMissing(): void
    {
        self::assertSame('', $this->makeBlock(0)->getHintText());
    }

    public function testGetHintTextReturnsEmptyStringWhenDataIsNotAString(): void
    {
        $block = $this->makeBlock(0);
        $block->setData('hint_text', 42);

        self::assertSame('', $block->getHintText());
    }

    public function testGetCtaLabelReturnsTheDataValue(): void
    {
        $block = $this->makeBlock(0);
        $block->setData('cta_label', 'Create Campaign');

        self::assertSame('Create Campaign', $block->getCtaLabel());
    }

    public function testGetCtaLabelReturnsEmptyStringWhenDataIsMissing(): void
    {
        self::assertSame('', $this->makeBlock(0)->getCtaLabel());
    }

    public function testGetCtaUrlBuildsTheUrlFromTheCtaPath(): void
    {
        $block = $this->makeBlock(0);
        $block->setData('cta_path', '*/campaign/new');
        $this->urlBuilder->method('getUrl')->willReturnMap([
            ['*/campaign/new', [], 'https://example.com/admin/ordo/campaign/new/'],
        ]);

        self::assertSame('https://example.com/admin/ordo/campaign/new/', $block->getCtaUrl());
    }

    public function testGetCtaUrlBuildsFromAnEmptyPathWhenDataIsMissing(): void
    {
        $block = $this->makeBlock(0);
        $this->urlBuilder->method('getUrl')->willReturnMap([
            ['', [], 'https://example.com/'],
        ]);

        self::assertSame('https://example.com/', $block->getCtaUrl());
    }
}
