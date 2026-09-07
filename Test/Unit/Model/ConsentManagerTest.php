<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model;

use Ordo\Automation\Model\ConsentChannel;
use Ordo\Automation\Model\ConsentManager;
use Ordo\Automation\Model\CustomerConsent;
use Ordo\Automation\Model\CustomerConsentFactory;
use Ordo\Automation\Model\ResourceModel\CustomerConsent as CustomerConsentResource;
use Ordo\Automation\Model\ResourceModel\CustomerConsent\Collection as CustomerConsentCollection;
use Ordo\Automation\Model\ResourceModel\CustomerConsent\CollectionFactory as CustomerConsentCollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class ConsentManagerTest extends TestCase
{
    private CustomerConsentCollectionFactory&\PHPUnit\Framework\MockObject\MockObject $collectionFactory;
    private CustomerConsentFactory&\PHPUnit\Framework\MockObject\MockObject $consentFactory;
    private CustomerConsentResource&\PHPUnit\Framework\MockObject\MockObject $consentResource;
    private ConsentManager $manager;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CustomerConsentCollectionFactory::class);
        $this->consentFactory = $this->createMock(CustomerConsentFactory::class);
        $this->consentResource = $this->createMock(CustomerConsentResource::class);
        $this->manager = new ConsentManager($this->collectionFactory, $this->consentFactory, $this->consentResource);
    }

    private function makeCollection(?CustomerConsent $consent): CustomerConsentCollection
    {
        $collection = $this->createStub(CustomerConsentCollection::class);
        $collection->method('addCustomerAndChannelFilter')->willReturnSelf();
        $collection->method('addCustomerFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn(
            $consent ?? $this->createStub(CustomerConsent::class)
        );
        $collection->method('getIterator')->willReturn(new \ArrayIterator($consent ? [$consent] : []));

        return $collection;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHasConsentReturnsTrueWhenNoRowExists(): void
    {
        $noRow = $this->createStub(CustomerConsent::class);
        $noRow->method('getId')->willReturn(null);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection($noRow));

        self::assertTrue($this->manager->hasConsent(42, ConsentChannel::Email));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHasConsentReturnsFalseWhenExplicitOptOutRowExists(): void
    {
        $row = $this->createStub(CustomerConsent::class);
        $row->method('getId')->willReturn(1);
        $row->method('isConsented')->willReturn(false);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection($row));

        self::assertFalse($this->manager->hasConsent(42, ConsentChannel::Email));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHasConsentReturnsTrueWhenExplicitOptInRowExists(): void
    {
        $row = $this->createStub(CustomerConsent::class);
        $row->method('getId')->willReturn(1);
        $row->method('isConsented')->willReturn(true);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection($row));

        self::assertTrue($this->manager->hasConsent(42, ConsentChannel::Email));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSetConsentCreatesNewRowWhenNoneExists(): void
    {
        $noRow = $this->createStub(CustomerConsent::class);
        $noRow->method('getId')->willReturn(null);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection($noRow));

        $newConsent = $this->createMock(CustomerConsent::class);
        $newConsent->expects(self::once())->method('setCustomerId')->with(42);
        $newConsent->expects(self::once())->method('setChannel')->with(ConsentChannel::Sms->value);
        $newConsent->expects(self::once())->method('setConsented')->with(false);
        $newConsent->expects(self::once())->method('setSource')->with('admin');
        $this->consentFactory->method('create')->willReturn($newConsent);
        $this->consentResource->expects(self::once())->method('save')->with($newConsent);

        $this->manager->setConsent(42, ConsentChannel::Sms, false, 'admin');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSetConsentUpdatesExistingRow(): void
    {
        $existing = $this->createMock(CustomerConsent::class);
        $existing->method('getId')->willReturn(5);
        $existing->expects(self::once())->method('setConsented')->with(true);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection($existing));

        $this->consentFactory->expects(self::never())->method('create');
        $this->consentResource->expects(self::once())->method('save')->with($existing);

        $this->manager->setConsent(42, ConsentChannel::Sms, true, 'unsubscribe_link');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetConsentStatesReturnsChannelMap(): void
    {
        $row = $this->createStub(CustomerConsent::class);
        $row->method('getChannel')->willReturn('sms');
        $row->method('isConsented')->willReturn(false);
        $this->collectionFactory->method('create')->willReturn($this->makeCollection($row));

        self::assertSame(['sms' => false], $this->manager->getConsentStates(42));
    }
}
