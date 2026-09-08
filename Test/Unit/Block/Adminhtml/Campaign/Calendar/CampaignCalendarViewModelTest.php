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

    /**
     * loadTimelineByCampaignId() must keep every campaign's own timeline, not just the first -
     * same truncation risk as testGetCampaignsReturnsEveryCampaignNotJustTheFirst, here for the
     * per-campaign_id action-timeline map getCampaigns() populates in one query.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetCampaignsPopulatesActionTimelinesForEveryCampaignNotJustTheFirst(): void
    {
        $campaignOne = $this->createStub(Campaign::class);
        $campaignOne->method('getEntityId')->willReturn(5);
        $campaignTwo = $this->createStub(Campaign::class);
        $campaignTwo->method('getEntityId')->willReturn(9);

        $collection = $this->createStub(CampaignCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$campaignOne, $campaignTwo]));
        $campaignCollectionFactory = $this->createStub(CampaignCollectionFactory::class);
        $campaignCollectionFactory->method('create')->willReturn($collection);

        $triggerCollection = $this->createStub(CampaignTriggerCollection::class);
        $triggerCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $campaignTriggerCollectionFactory = $this->createStub(CampaignTriggerCollectionFactory::class);
        $campaignTriggerCollectionFactory->method('create')->willReturn($triggerCollection);

        $actionOne = $this->createStub(CampaignAction::class);
        $actionOne->method('getCampaignId')->willReturn(5);
        $actionOne->method('getType')->willReturn('add_tag');
        $actionOne->method('getDelayMinutes')->willReturn(0);

        $actionTwo = $this->createStub(CampaignAction::class);
        $actionTwo->method('getCampaignId')->willReturn(9);
        $actionTwo->method('getType')->willReturn('send_email');
        $actionTwo->method('getDelayMinutes')->willReturn(0);

        $actionCollection = $this->createStub(CampaignActionCollection::class);
        $actionCollection->method('getIterator')->willReturn(new \ArrayIterator([$actionOne, $actionTwo]));
        $campaignActionCollectionFactory = $this->createStub(CampaignActionCollectionFactory::class);
        $campaignActionCollectionFactory->method('create')->willReturn($actionCollection);

        $viewModel = $this->makeViewModel(
            $campaignCollectionFactory,
            $campaignTriggerCollectionFactory,
            $campaignActionCollectionFactory
        );
        $viewModel->getCampaigns();

        self::assertSame('add_tag', $viewModel->getActionTimelineForCampaign(5)[0]['type']);
        self::assertSame('send_email', $viewModel->getActionTimelineForCampaign(9)[0]['type']);
    }

    public function testFormatOffsetMinutesReturnsImmediateForZeroOrNegative(): void
    {
        $viewModel = $this->makeViewModel();

        self::assertSame('immediate', $viewModel->formatOffsetMinutes(0));
        self::assertSame('immediate', $viewModel->formatOffsetMinutes(-5));
    }

    public function testFormatOffsetMinutesPrefersWholeDays(): void
    {
        $viewModel = $this->makeViewModel();

        self::assertSame('+1 day', $viewModel->formatOffsetMinutes(1440));
        self::assertSame('+2 days', $viewModel->formatOffsetMinutes(2880));
    }

    public function testFormatOffsetMinutesPrefersWholeHoursOverRawMinutes(): void
    {
        $viewModel = $this->makeViewModel();

        self::assertSame('+1 hour', $viewModel->formatOffsetMinutes(60));
        self::assertSame('+3 hours', $viewModel->formatOffsetMinutes(180));
    }

    public function testFormatOffsetMinutesFallsBackToRawMinutesWhenNotAWholeHourOrDay(): void
    {
        $viewModel = $this->makeViewModel();

        self::assertSame('+90 min', $viewModel->formatOffsetMinutes(90));
        self::assertSame('+45 min', $viewModel->formatOffsetMinutes(45));
    }

    /**
     * intdiv($minutes, 1440) at large multiples - regression-guards the exact divisor, not just
     * "some plausible day count", since 1439 (a mutation-testing-caught off-by-one) agrees with
     * 1440 for every multiple below 1439 days and only diverges at this boundary
     * (1440 * 1439 = 1439 whole days under the correct divisor, 1440 under the wrong one).
     */
    public function testFormatOffsetMinutesUsesExactlyOneThousandFourHundredFortyMinutesPerDay(): void
    {
        $viewModel = $this->makeViewModel();

        self::assertSame('+1439 days', $viewModel->formatOffsetMinutes(1440 * 1439));
    }

    /**
     * Same off-by-one guard as above, for the hours divisor: intdiv($minutes, 60) vs. an
     * accidental 59 first diverges at 59 whole hours (59 * 60 = 3540 minutes).
     */
    public function testFormatOffsetMinutesUsesExactlySixtyMinutesPerHour(): void
    {
        $viewModel = $this->makeViewModel();

        self::assertSame('+59 hours', $viewModel->formatOffsetMinutes(59 * 60));
    }

    /**
     * getCampaigns() must return every campaign, not just the first - a real bug a careless
     * refactor (e.g. an accidental early "take the first match" shortcut) could introduce without
     * any single-campaign test ever noticing.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetCampaignsReturnsEveryCampaignNotJustTheFirst(): void
    {
        $campaignOne = $this->createStub(Campaign::class);
        $campaignOne->method('getEntityId')->willReturn(5);
        $campaignTwo = $this->createStub(Campaign::class);
        $campaignTwo->method('getEntityId')->willReturn(9);

        $collection = $this->createStub(CampaignCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$campaignOne, $campaignTwo]));
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

        self::assertSame([$campaignOne, $campaignTwo], $viewModel->getCampaigns());
    }

    /**
     * getEntityId() is typed ?int, so it can return null (a detached/not-yet-persisted entity) -
     * the explicit (int) cast before building $campaignIds must actually run, turning that null
     * into 0, or the addFieldToFilter('campaign_id', ['in' => $campaignIds]) call downstream ends
     * up with a literal null in the filter list instead.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetCampaignsCastsEntityIdsToIntBeforeFilteringTriggers(): void
    {
        $campaign = $this->createStub(Campaign::class);
        $campaign->method('getEntityId')->willReturn(null);

        $collection = $this->createStub(CampaignCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$campaign]));
        $campaignCollectionFactory = $this->createStub(CampaignCollectionFactory::class);
        $campaignCollectionFactory->method('create')->willReturn($collection);

        $triggerCollection = $this->createMock(CampaignTriggerCollection::class);
        $triggerCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $campaignTriggerCollectionFactory = $this->createStub(CampaignTriggerCollectionFactory::class);
        $campaignTriggerCollectionFactory->method('create')->willReturn($triggerCollection);
        // self::identicalTo(), not the loose equality with()'s bare-array form uses by default -
        // PHP's own 0 == null is true, so a bare ['in' => [0]] argument constraint would have let
        // an uncast null silently pass and never actually catch the missing (int) cast.
        $triggerCollection->expects(self::once())->method('addFieldToFilter')
            ->with('campaign_id', self::identicalTo(['in' => [0]]));

        $actionCollection = $this->createMock(CampaignActionCollection::class);
        $actionCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $campaignActionCollectionFactory = $this->createStub(CampaignActionCollectionFactory::class);
        $campaignActionCollectionFactory->method('create')->willReturn($actionCollection);
        $actionCollection->expects(self::once())->method('addCampaignIdsFilter')->with(self::identicalTo([0]));

        $viewModel = $this->makeViewModel(
            $campaignCollectionFactory,
            $campaignTriggerCollectionFactory,
            $campaignActionCollectionFactory
        );

        $viewModel->getCampaigns();
    }

    /**
     * loadTriggerLabelsByCampaignId() must actually filter by the requested campaign ids, not
     * hand the trigger collection an unfiltered/empty filter - otherwise every campaign would
     * show every other campaign's triggers.
     */
    public function testGetTriggerLabelsForCampaignFiltersByTheRequestedCampaignId(): void
    {
        $triggerCollection = $this->createMock(CampaignTriggerCollection::class);
        $triggerCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $triggerCollection->expects(self::once())->method('addFieldToFilter')->with('campaign_id', ['in' => [5]]);
        $campaignTriggerCollectionFactory = $this->createStub(CampaignTriggerCollectionFactory::class);
        $campaignTriggerCollectionFactory->method('create')->willReturn($triggerCollection);

        $viewModel = $this->makeViewModel(null, $campaignTriggerCollectionFactory);

        $viewModel->getTriggerLabelsForCampaign(5);
    }
}
