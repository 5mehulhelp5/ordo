<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Block\Adminhtml\Campaign\Calendar;

use Ordo\Automation\Api\Data\CampaignTriggerInterface;
use Ordo\Automation\Block\Adminhtml\Campaign\Calendar\CampaignCalendarViewModel;
use Ordo\Automation\Model\Campaign;
use Ordo\Automation\Model\Campaign\TypeLabels;
use Ordo\Automation\Model\CampaignAction;
use Ordo\Automation\Model\CampaignTrigger;
use Ordo\Automation\Model\Config\Source\TriggerEvent;
use Ordo\Automation\Model\ResourceModel\Campaign\Action\Collection as CampaignActionCollection;
use Ordo\Automation\Model\ResourceModel\Campaign\Action\CollectionFactory as CampaignActionCollectionFactory;
use Ordo\Automation\Model\ResourceModel\Campaign\Collection as CampaignCollection;
use Ordo\Automation\Model\ResourceModel\Campaign\CollectionFactory as CampaignCollectionFactory;
use Ordo\Automation\Model\ResourceModel\Campaign\Trigger\Collection as CampaignTriggerCollection;
use Ordo\Automation\Model\ResourceModel\Campaign\Trigger\CollectionFactory as CampaignTriggerCollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class CampaignCalendarViewModelTest extends TestCase
{
    private function makeViewModel(
        ?CampaignCollectionFactory $campaignCollectionFactory = null,
        ?CampaignTriggerCollectionFactory $campaignTriggerCollectionFactory = null,
        ?CampaignActionCollectionFactory $campaignActionCollectionFactory = null
    ): CampaignCalendarViewModel {
        return new CampaignCalendarViewModel(
            $campaignCollectionFactory ?? $this->createStub(CampaignCollectionFactory::class),
            $campaignTriggerCollectionFactory ?? $this->createStub(CampaignTriggerCollectionFactory::class),
            $campaignActionCollectionFactory ?? $this->createStub(CampaignActionCollectionFactory::class),
            new TriggerEvent(),
            new TypeLabels()
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetCampaignsOrdersByName(): void
    {
        $campaign = $this->createStub(Campaign::class);
        $campaign->method('getEntityId')->willReturn(5);

        $collection = $this->createMock(CampaignCollection::class);
        $collection->expects(self::once())->method('setOrder')->with('name', 'ASC');
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$campaign]));

        $campaignCollectionFactory = $this->createStub(CampaignCollectionFactory::class);
        $campaignCollectionFactory->method('create')->willReturn($collection);

        $triggerCollection = $this->createStub(CampaignTriggerCollection::class);
        $triggerCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $campaignTriggerCollectionFactory = $this->createStub(CampaignTriggerCollectionFactory::class);
        $campaignTriggerCollectionFactory->method('create')->willReturn($triggerCollection);

        $actionCollection = $this->createStub(CampaignActionCollection::class);
        $actionCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $campaignActionCollectionFactory = $this->createStub(CampaignActionCollectionFactory::class);
        $campaignActionCollectionFactory->method('create')->willReturn($actionCollection);

        $viewModel = $this->makeViewModel(
            $campaignCollectionFactory,
            $campaignTriggerCollectionFactory,
            $campaignActionCollectionFactory
        );

        self::assertSame([$campaign], $viewModel->getCampaigns());
    }

    /**
     * campaignIds is empty only via getCampaigns() itself, when the campaign collection is
     * empty - the per-campaign lookups below never build this case on their own since they
     * always query with a single, non-empty campaign id.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetCampaignsWithNoCampaignsCachesEmptyLookups(): void
    {
        $collection = $this->createStub(CampaignCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $campaignCollectionFactory = $this->createStub(CampaignCollectionFactory::class);
        $campaignCollectionFactory->method('create')->willReturn($collection);

        $campaignTriggerCollectionFactory = $this->createMock(CampaignTriggerCollectionFactory::class);
        $campaignTriggerCollectionFactory->expects(self::never())->method('create');
        $campaignActionCollectionFactory = $this->createMock(CampaignActionCollectionFactory::class);
        $campaignActionCollectionFactory->expects(self::never())->method('create');

        $viewModel = $this->makeViewModel(
            $campaignCollectionFactory,
            $campaignTriggerCollectionFactory,
            $campaignActionCollectionFactory
        );

        self::assertSame([], $viewModel->getCampaigns());
        self::assertSame('No trigger configured', $viewModel->getTriggerLabelsForCampaign(5));
        self::assertSame([], $viewModel->getActionTimelineForCampaign(5));
    }

    public function testGetTriggerLabelsForCampaignJoinsMultipleTriggers(): void
    {
        $triggerOne = $this->createStub(CampaignTrigger::class);
        $triggerOne->method('getCampaignId')->willReturn(5);
        $triggerOne->method('getTriggerEvent')->willReturn(CampaignTriggerInterface::TRIGGER_ORDER_PLACED);

        $triggerTwo = $this->createStub(CampaignTrigger::class);
        $triggerTwo->method('getCampaignId')->willReturn(5);
        $triggerTwo->method('getTriggerEvent')->willReturn(CampaignTriggerInterface::TRIGGER_TAG_ADDED);

        $triggerCollection = $this->createStub(CampaignTriggerCollection::class);
        $triggerCollection->method('getIterator')->willReturn(new \ArrayIterator([$triggerOne, $triggerTwo]));
        $campaignTriggerCollectionFactory = $this->createStub(CampaignTriggerCollectionFactory::class);
        $campaignTriggerCollectionFactory->method('create')->willReturn($triggerCollection);

        $viewModel = $this->makeViewModel(null, $campaignTriggerCollectionFactory);

        self::assertSame('Order Placed, Tag Added', $viewModel->getTriggerLabelsForCampaign(5));
    }

    public function testGetTriggerLabelsForCampaignFallsBackWhenNoneConfigured(): void
    {
        $triggerCollection = $this->createStub(CampaignTriggerCollection::class);
        $triggerCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $campaignTriggerCollectionFactory = $this->createStub(CampaignTriggerCollectionFactory::class);
        $campaignTriggerCollectionFactory->method('create')->willReturn($triggerCollection);

        $viewModel = $this->makeViewModel(null, $campaignTriggerCollectionFactory);

        self::assertSame('No trigger configured', $viewModel->getTriggerLabelsForCampaign(5));
    }

    /**
     * The whole point of this view model: each action's own delay_minutes is the wait after
     * the PREVIOUS action (CampaignDispatcher::runActionsFrom()), so the rendered "offset"
     * must be a running sum, not each row's raw delay_minutes repeated.
     */
    public function testGetActionTimelineForCampaignComputesCumulativeOffset(): void
    {
        $firstAction = $this->createStub(CampaignAction::class);
        $firstAction->method('getCampaignId')->willReturn(5);
        $firstAction->method('getType')->willReturn('add_tag');
        $firstAction->method('getDelayMinutes')->willReturn(0);

        $secondAction = $this->createStub(CampaignAction::class);
        $secondAction->method('getCampaignId')->willReturn(5);
        $secondAction->method('getType')->willReturn('send_email');
        $secondAction->method('getDelayMinutes')->willReturn(60);

        $thirdAction = $this->createStub(CampaignAction::class);
        $thirdAction->method('getCampaignId')->willReturn(5);
        $thirdAction->method('getType')->willReturn('generate_coupon');
        $thirdAction->method('getDelayMinutes')->willReturn(30);

        $actionCollection = $this->createStub(CampaignActionCollection::class);
        $actionCollection->method('getIterator')->willReturn(
            new \ArrayIterator([$firstAction, $secondAction, $thirdAction])
        );
        $campaignActionCollectionFactory = $this->createStub(CampaignActionCollectionFactory::class);
        $campaignActionCollectionFactory->method('create')->willReturn($actionCollection);

        $viewModel = $this->makeViewModel(null, null, $campaignActionCollectionFactory);

        self::assertSame(
            [
                ['type' => 'add_tag', 'label' => 'Add Tag', 'delay_minutes' => 0, 'offset_minutes' => 0],
                ['type' => 'send_email', 'label' => 'Send Email', 'delay_minutes' => 60, 'offset_minutes' => 60],
                [
                    'type' => 'generate_coupon',
                    'label' => 'Generate Coupon',
                    'delay_minutes' => 30,
                    'offset_minutes' => 90,
                ],
            ],
            $viewModel->getActionTimelineForCampaign(5)
        );
    }

    public function testGetActionTimelineForCampaignReturnsEmptyArrayWhenNoActions(): void
    {
        $actionCollection = $this->createStub(CampaignActionCollection::class);
        $actionCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $campaignActionCollectionFactory = $this->createStub(CampaignActionCollectionFactory::class);
        $campaignActionCollectionFactory->method('create')->willReturn($actionCollection);

        $viewModel = $this->makeViewModel(null, null, $campaignActionCollectionFactory);

        self::assertSame([], $viewModel->getActionTimelineForCampaign(5));
    }
}
