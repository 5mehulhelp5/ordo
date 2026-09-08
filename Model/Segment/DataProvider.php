<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Segment;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Ordo\Automation\Model\ResourceModel\Segment\CollectionFactory as SegmentCollectionFactory;
use Ordo\Automation\Model\ResourceModel\Segment\Condition\CollectionFactory as SegmentConditionCollectionFactory;

/**
 * Feeds the segment edit form, including its one dynamicRows section (conditions) — same
 * approach as Model\Campaign\DataProvider.
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
        SegmentCollectionFactory $collectionFactory,
        private readonly SegmentConditionCollectionFactory $segmentConditionCollectionFactory,
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

        foreach ($this->collection->getItems() as $segment) {
            /** @var array<string, mixed> $segmentData */
            $segmentData = $segment->getData();
            $segmentId = (int) $segment->getEntityId();

            // The conditions dynamicRows component's own name AND its own <dataScope> are both
            // "conditions" (see ordo_segment_form.xml) — Magento_Ui/js/dynamic-rows/dynamic-rows.js
            // reads/writes each row at `${dataScope}.${index}.${rowIndex}...`, so the loaded data
            // needs that same double nesting, not a flat array, or the component's own elems
            // stay empty on an existing segment's edit reload (confirmed via a real CI run: the
            // dynamicRows component itself reported visible=true, elems.length=0).
            $segmentData['conditions'] = ['conditions' => $this->loadConditionRows($segmentId)];

            $this->loadedData[$segmentId] = $segmentData;
        }

        /** @var array<string, mixed>|null $persisted */
        $persisted = $this->dataPersistor->get('ordo_segment');
        if ($persisted) {
            $segmentId = (int) ($persisted['entity_id'] ?? 0);
            if ($segmentId) {
                $this->loadedData[$segmentId] = $persisted;
            }
            $this->dataPersistor->clear('ordo_segment');
        }

        return $this->loadedData;
    }

    /**
     * Spreads the saved params back into the row's dedicated fields (tag, amount, ...) too —
     * not just params_json — so the switcherConfig fields in ordo_segment_form.xml (mirrored
     * from the campaign form's own — see Model\Campaign\DataProvider::loadChildRows(), same
     * reasoning) pre-populate correctly when editing an existing segment, instead of only
     * showing the raw JSON with every dedicated field blank.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadConditionRows(int $segmentId): array
    {
        $collection = $this->segmentConditionCollectionFactory->create();
        $collection->addSegmentFilter($segmentId);

        $rows = [];
        foreach ($collection as $row) {
            $paramsJson = $row->getParamsJson();
            $decoded = json_decode($paramsJson, true);

            /** @var array<string, mixed> $rowData */
            $rowData = [
                'type' => $row->getType(),
                'params_json' => $paramsJson,
            ];

            if (is_array($decoded)) {
                $rowData += $decoded;
            }

            $rows[] = $rowData;
        }

        return $rows;
    }
}
