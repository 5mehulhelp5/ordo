<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\WhatsAppTemplate;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Ordo\Automation\Model\ResourceModel\WhatsAppTemplate\CollectionFactory as WhatsAppTemplateCollectionFactory;

/**
 * Feeds the WhatsApp template edit form. Flat entity, no child rows — same shape as
 * AdAudience\DataProvider.
 */
class DataProvider extends AbstractDataProvider
{
    protected ?array $loadedData = null;

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        WhatsAppTemplateCollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $this->loadedData = [];

        foreach ($this->collection->getItems() as $template) {
            $id = (int) $template->getEntityId();
            $this->loadedData[$id] = $template->getData();
        }

        /** @var array<string, mixed>|null $persisted */
        $persisted = $this->dataPersistor->get('ordo_whatsapp_template');
        if ($persisted) {
            $id = (int) ($persisted['entity_id'] ?? 0);
            if ($id) {
                $this->loadedData[$id] = $persisted;
            }
            $this->dataPersistor->clear('ordo_whatsapp_template');
        }

        return $this->loadedData;
    }
}
