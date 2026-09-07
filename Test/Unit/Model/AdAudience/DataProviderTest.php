<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\AdAudience;

use Magento\Framework\App\Request\DataPersistorInterface;
use Ordo\Automation\Model\AdAudience;
use Ordo\Automation\Model\AdAudience\DataProvider;
use Ordo\Automation\Model\ResourceModel\AdAudience\Collection as AdAudienceCollection;
use Ordo\Automation\Model\ResourceModel\AdAudience\CollectionFactory as AdAudienceCollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    private DataPersistorInterface $dataPersistor;

    protected function setUp(): void
    {
        $this->dataPersistor = $this->createMock(DataPersistorInterface::class);
    }

    private function makeProvider(AdAudienceCollection $collection): DataProvider
    {
        $collectionFactory = $this->createStub(AdAudienceCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        return new DataProvider(
            'ordo_adaudience_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $this->dataPersistor
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetDataLoadsFromCollectionKeyedByEntityId(): void
    {
        $adAudience = $this->createStub(AdAudience::class);
        $adAudience->method('getEntityId')->willReturn(3);
        $adAudience->method('getData')->willReturn(['entity_id' => 3, 'name' => 'Test Audience']);

        $collection = $this->createStub(AdAudienceCollection::class);
        $collection->method('getItems')->willReturn([$adAudience]);

        $this->dataPersistor->method('get')->willReturn(null);

        $provider = $this->makeProvider($collection);
        $data = $provider->getData();

        self::assertSame(['entity_id' => 3, 'name' => 'Test Audience'], $data[3]);

        // Second call must hit the cached $loadedData branch, not reload from the collection.
        self::assertSame($data, $provider->getData());
    }

    public function testGetDataAppliesPersistedDataAndClearsIt(): void
    {
        $collection = $this->createStub(AdAudienceCollection::class);
        $collection->method('getItems')->willReturn([]);

        $this->dataPersistor->method('get')
            ->willReturnMap([['ordo_ad_audience', ['entity_id' => 5, 'name' => 'Persisted']]]);
        $this->dataPersistor->expects(self::once())->method('clear')->with('ordo_ad_audience');

        $provider = $this->makeProvider($collection);
        $data = $provider->getData();

        self::assertSame(['entity_id' => 5, 'name' => 'Persisted'], $data[5]);
    }

    public function testGetDataIgnoresPersistedDataWithoutEntityId(): void
    {
        $collection = $this->createStub(AdAudienceCollection::class);
        $collection->method('getItems')->willReturn([]);

        $this->dataPersistor->method('get')->willReturn(['name' => 'Persisted']);
        $this->dataPersistor->expects(self::once())->method('clear')->with('ordo_ad_audience');

        $provider = $this->makeProvider($collection);

        self::assertSame([], $provider->getData());
    }
}
