<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Push;

use ArrayIterator;
use Magento\Framework\Exception\AlreadyExistsException;
use Ordo\Automation\Model\Push\PushSubscriptionManager;
use Ordo\Automation\Model\PushSubscription;
use Ordo\Automation\Model\ResourceModel\PushSubscription as PushSubscriptionResource;
use Ordo\Automation\Model\ResourceModel\PushSubscription\Collection as PushSubscriptionCollection;
use Ordo\Automation\Model\ResourceModel\PushSubscription\CollectionFactory as PushSubscriptionCollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PushSubscriptionManagerTest extends TestCase
{
    private PushSubscriptionResource&MockObject $resource;
    private PushSubscriptionCollectionFactory&MockObject $collectionFactory;
    private PushSubscriptionManager $manager;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(PushSubscriptionResource::class);
        $this->collectionFactory = $this->createMock(PushSubscriptionCollectionFactory::class);
        $this->manager = new PushSubscriptionManager($this->resource, $this->collectionFactory);
    }

    private function makeCollection(array $items, ?PushSubscription $firstItem = null): PushSubscriptionCollection
    {
        $collection = $this->createStub(PushSubscriptionCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('addCustomerFilter')->willReturnSelf();
        $collection->method('addVisitorFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($firstItem ?? $this->createStub(PushSubscription::class));
        $collection->method('getIterator')->willReturn(new ArrayIterator($items));
        $collection->method('getItems')->willReturn($items);

        return $collection;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRegisterCreatesNewRowWhenNoneExists(): void
    {
        $noRow = $this->createMock(PushSubscription::class);
        $noRow->method('getId')->willReturn(null);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection([], $noRow));

        $noRow->expects(self::once())->method('setEndpoint')->with('https://push.example.com/1');
        $noRow->expects(self::once())->method('setP256dhKey')->with('p256dh-key');
        $noRow->expects(self::once())->method('setAuthKey')->with('auth-key');
        $noRow->expects(self::once())->method('setVisitorId')->with('visitor-1');
        $noRow->expects(self::once())->method('setCreatedAt');
        $this->resource->expects(self::once())->method('save')->with($noRow);

        $this->manager->register('https://push.example.com/1', 'p256dh-key', 'auth-key', null, 'visitor-1');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRegisterUpdatesExistingRowInsteadOfCreatingDuplicate(): void
    {
        $existing = $this->createMock(PushSubscription::class);
        $existing->method('getId')->willReturn(5);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection([], $existing));

        $existing->expects(self::never())->method('setCreatedAt');
        $existing->expects(self::never())->method('setVisitorId');
        $existing->expects(self::once())->method('setLastSeenAt');
        $this->resource->expects(self::once())->method('save')->with($existing);

        $this->manager->register('https://push.example.com/1', 'p256dh-key', 'auth-key', null, 'visitor-1');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRegisterNeverOverwritesKnownCustomerIdWithNull(): void
    {
        $existing = $this->createMock(PushSubscription::class);
        $existing->method('getId')->willReturn(5);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection([], $existing));

        $existing->expects(self::never())->method('setCustomerId');

        $this->manager->register('https://push.example.com/1', 'p256dh-key', 'auth-key', null, null);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRegisterSetsCustomerIdWhenGiven(): void
    {
        $existing = $this->createMock(PushSubscription::class);
        $existing->method('getId')->willReturn(5);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection([], $existing));

        $existing->expects(self::once())->method('setCustomerId')->with(42);

        $this->manager->register('https://push.example.com/1', 'p256dh-key', 'auth-key', 42, null);
    }

    /**
     * The "shared computer" scenario the class docblock's upsert reasoning implies: a second,
     * different customer registering on the same already-known browser/subscription overwrites
     * the stored customer_id outright - a deliberate design choice (Web Push has no concept of
     * "logged out of this subscription"), not a bug, but worth a regression test since it's the
     * closest thing to a real data-attribution edge case in this class.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testRegisterOverwritesCustomerIdWhenADifferentCustomerRegistersOnTheSameSubscription(): void
    {
        $existing = $this->createMock(PushSubscription::class);
        $existing->method('getId')->willReturn(5);
        $existing->method('getCustomerId')->willReturn(1);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection([], $existing));

        $existing->expects(self::once())->method('setCustomerId')->with(2);

        $this->manager->register('https://push.example.com/1', 'p256dh-key', 'auth-key', 2, null);
    }

    /**
     * Two concurrent register() calls for the same brand-new endpoint (e.g. push-sw.js's own
     * pushsubscriptionchange handler firing at the same moment tracker.js's subscribeToPush()
     * resolves) both see an id-less model and both attempt an insert - the second one must lose
     * the unique-constraint race gracefully (re-fetch and update) rather than surface an error.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testRegisterRecoversFromAlreadyExistsExceptionByUpdatingTheWinningRow(): void
    {
        $noRow = $this->createStub(PushSubscription::class);
        $noRow->method('getId')->willReturn(null);

        $winningRow = $this->createMock(PushSubscription::class);
        $winningRow->method('getId')->willReturn(9);

        $callCount = 0;
        $this->collectionFactory->method('create')->willReturnCallback(function () use (&$callCount, $noRow, $winningRow) {
            $callCount++;
            return $callCount === 1 ? $this->makeCollection([], $noRow) : $this->makeCollection([], $winningRow);
        });

        $this->resource->expects(self::exactly(2))->method('save')
            ->willReturnCallback(function ($subscription) use ($noRow) {
                if ($subscription === $noRow) {
                    throw new AlreadyExistsException(__('duplicate'));
                }
            });

        $winningRow->expects(self::once())->method('setP256dhKey')->with('p256dh-key');
        $winningRow->expects(self::once())->method('setAuthKey')->with('auth-key');
        // The retry path must not re-run the "new row" branch against the row someone else just
        // inserted - it already has an id and its own created_at/visitor_id.
        $winningRow->expects(self::never())->method('setCreatedAt');
        $winningRow->expects(self::never())->method('setVisitorId');

        $this->manager->register('https://push.example.com/1', 'p256dh-key', 'auth-key', null, 'visitor-1');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testUnregisterDeletesEveryMatchingRow(): void
    {
        $row = $this->createStub(PushSubscription::class);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection([$row]));

        $this->resource->expects(self::once())->method('delete')->with($row);

        $this->manager->unregister('https://push.example.com/1');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAttributeVisitorToCustomerUpdatesEachMatchingRow(): void
    {
        $row = $this->createMock(PushSubscription::class);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection([$row]));

        $row->expects(self::once())->method('setCustomerId')->with(42);
        $this->resource->expects(self::once())->method('save')->with($row);

        $this->manager->attributeVisitorToCustomer('visitor-1', 42);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetForCustomerReturnsAllMatchingSubscriptions(): void
    {
        $row1 = $this->createStub(PushSubscription::class);
        $row2 = $this->createStub(PushSubscription::class);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection([$row1, $row2]));

        self::assertSame([$row1, $row2], $this->manager->getForCustomer(42));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteDelegatesToResource(): void
    {
        $row = $this->createStub(PushSubscription::class);
        $this->resource->expects(self::once())->method('delete')->with($row);

        $this->manager->delete($row);
    }

    /**
     * A brand-new registration that pushes this owner over MAX_SUBSCRIPTIONS_PER_OWNER (20)
     * evicts the oldest surplus rows rather than growing the table without bound - without this,
     * an attacker (or a buggy client retry loop) hammering RegisterPushSubscription could fill
     * ordo_push_subscription indefinitely.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testRegisterEvictsOldestSubscriptionsBeyondTheCapForANewRegistration(): void
    {
        $noRow = $this->createStub(PushSubscription::class);
        $noRow->method('getId')->willReturn(null);

        $newRow = $this->createStub(PushSubscription::class);
        $newRow->method('getId')->willReturn(100);

        $oldest = $this->createStub(PushSubscription::class);
        $oldest->method('getId')->willReturn(1);

        // 21 existing rows (over the cap of 20) plus the new one about to be saved.
        $overflowItems = array_merge([$oldest], array_fill(0, 20, $this->createStub(PushSubscription::class)));

        $callCount = 0;
        $this->collectionFactory->method('create')->willReturnCallback(
            function () use (&$callCount, $noRow, $newRow, $overflowItems) {
                $callCount++;
                if ($callCount === 1) {
                    return $this->makeCollection([], $noRow);
                }
                return $this->makeCollection($overflowItems, $newRow);
            }
        );
        $this->resource->expects(self::once())->method('delete')->with($oldest);

        $this->manager->register('https://push.example.com/new', 'p256dh-key', 'auth-key', 42, null);
    }
}
