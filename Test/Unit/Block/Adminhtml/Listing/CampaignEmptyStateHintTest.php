<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Block\Adminhtml\Listing;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Ordo\Automation\Block\Adminhtml\Listing\CampaignEmptyStateHint;
use Ordo\Automation\Model\ResourceModel\Campaign\Collection;
use Ordo\Automation\Model\ResourceModel\Campaign\CollectionFactory;
use PHPUnit\Framework\TestCase;

/**
 * getCollectionSize() is the only line CampaignEmptyStateHint itself adds over its abstract
 * parent (already fully covered by AbstractEmptyStateHintTest) - this only proves that one line
 * wires the right factory through to isEmptyState() correctly, not every inherited behavior again.
 */
class CampaignEmptyStateHintTest extends TestCase
{
    protected function setUp(): void
    {
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($this->createStub(\stdClass::class));
        ObjectManager::setInstance($objectManager);
    }

    protected function tearDown(): void
    {
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));
    }

    private function makeBlock(CollectionFactory $collectionFactory): CampaignEmptyStateHint
    {
        $context = $this->createStub(Context::class);

        return new CampaignEmptyStateHint($context, $collectionFactory);
    }

    public function testIsEmptyStateReflectsTheCampaignCollectionSize(): void
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getSize')->willReturn(0);

        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        self::assertTrue($this->makeBlock($collectionFactory)->isEmptyState());
    }

    public function testIsEmptyStateIsFalseWhenCampaignsExist(): void
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getSize')->willReturn(5);

        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        self::assertFalse($this->makeBlock($collectionFactory)->isEmptyState());
    }
}
