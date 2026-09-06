<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Ordo\Automation\Model\ResourceModel\Segment\CollectionFactory as SegmentCollectionFactory;

/**
 * Every existing segment, by id/name — the picker for an ordo_ad_audience row's segment_id
 * field. Deliberately lists ALL segments, not just enabled ones: an admin configuring an ad
 * audience sync ahead of enabling the segment itself is a normal setup order, not an error.
 */
class SegmentOptions implements OptionSourceInterface
{
    public function __construct(
        private readonly SegmentCollectionFactory $segmentCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->segmentCollectionFactory->create() as $segment) {
            /** @var \Ordo\Automation\Model\Segment $segment */
            $options[] = ['value' => (int) $segment->getEntityId(), 'label' => $segment->getName()];
        }

        return $options;
    }
}
