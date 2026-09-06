<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\AdAudience;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Ordo\Automation\Model\ResourceModel\AdAudience\CollectionFactory as AdAudienceCollectionFactory;

/**
 * Feeds the ad audience edit form. Flat entity, no child rows — same shape as ScoreRule\DataProvider.
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
        AdAudienceCollectionFactory $collectionFactory,
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

        foreach ($this->collection->getItems() as $adAudience) {
            $id = (int) $adAudience->getEntityId();
            $this->loadedData[$id] = $adAudience->getData();
        }

        /** @var array<string, mixed>|null $persisted */
        $persisted = $this->dataPersistor->get('ordo_ad_audience');
        if ($persisted) {
            $id = (int) ($persisted['entity_id'] ?? 0);
            if ($id) {
                $this->loadedData[$id] = $persisted;
            }
            $this->dataPersistor->clear('ordo_ad_audience');
        }

        return $this->loadedData;
    }
}
