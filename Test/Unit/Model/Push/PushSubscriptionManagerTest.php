<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Push;

use Ordo\Automation\Model\Push\PushSubscriptionManager;
use Ordo\Automation\Model\PushSubscription;
use Ordo\Automation\Model\ResourceModel\PushSubscription as PushSubscriptionResource;
use Ordo\Automation\Model\ResourceModel\PushSubscription\Collection as PushSubscriptionCollection;
use Ordo\Automation\Model\ResourceModel\PushSubscription\CollectionFactory as PushSubscriptionCollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class PushSubscriptionManagerTest extends TestCase
{
    private PushSubscriptionResource&\PHPUnit\Framework\MockObject\MockObject $resource;
    private PushSubscriptionCollectionFactory&\PHPUnit\Framework\MockObject\MockObject $collectionFactory;
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
        $collection->method('getFirstItem')->willReturn($firstItem ?? $this->createStub(PushSubscription::class));
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
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
}
