<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\WhatsAppTemplate;

use Magento\Framework\App\Request\DataPersistorInterface;
use Ordo\Automation\Model\ResourceModel\WhatsAppTemplate\Collection as WhatsAppTemplateCollection;
use Ordo\Automation\Model\ResourceModel\WhatsAppTemplate\CollectionFactory as WhatsAppTemplateCollectionFactory;
use Ordo\Automation\Model\WhatsAppTemplate;
use Ordo\Automation\Model\WhatsAppTemplate\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class DataProviderTest extends TestCase
{
    private DataPersistorInterface $dataPersistor;

    protected function setUp(): void
    {
        $this->dataPersistor = $this->createMock(DataPersistorInterface::class);
    }

    private function makeProvider(WhatsAppTemplateCollection $collection): DataProvider
    {
        $collectionFactory = $this->createStub(WhatsAppTemplateCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        return new DataProvider(
            'ordo_whatsapptemplate_form_data_source',
            'entity_id',
            'entity_id',
            $collectionFactory,
            $this->dataPersistor
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetDataLoadsFromCollectionKeyedByEntityId(): void
    {
        $template = $this->createStub(WhatsAppTemplate::class);
        $template->method('getEntityId')->willReturn(3);
        $template->method('getData')->willReturn(['entity_id' => 3, 'name' => 'Order Shipped']);

        $collection = $this->createStub(WhatsAppTemplateCollection::class);
        $collection->method('getItems')->willReturn([$template]);

        $this->dataPersistor->method('get')->willReturn(null);

        $provider = $this->makeProvider($collection);
        $data = $provider->getData();

        self::assertSame(['entity_id' => 3, 'name' => 'Order Shipped'], $data[3]);
        self::assertSame($data, $provider->getData());
    }

    public function testGetDataAppliesPersistedDataAndClearsIt(): void
    {
        $collection = $this->createStub(WhatsAppTemplateCollection::class);
        $collection->method('getItems')->willReturn([]);

        $this->dataPersistor->method('get')
            ->willReturnMap([['ordo_whatsapp_template', ['entity_id' => 5, 'name' => 'Persisted']]]);
        $this->dataPersistor->expects(self::once())->method('clear')->with('ordo_whatsapp_template');

        $provider = $this->makeProvider($collection);
        $data = $provider->getData();

        self::assertSame(['entity_id' => 5, 'name' => 'Persisted'], $data[5]);
    }

    public function testGetDataIgnoresPersistedDataWithoutEntityId(): void
    {
        $collection = $this->createStub(WhatsAppTemplateCollection::class);
        $collection->method('getItems')->willReturn([]);

        $this->dataPersistor->method('get')->willReturn(['name' => 'Persisted']);
        $this->dataPersistor->expects(self::once())->method('clear')->with('ordo_whatsapp_template');

        $provider = $this->makeProvider($collection);

        self::assertSame([], $provider->getData());
    }
}
