<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * The "Segment matches" / condition_logic dropdown on ordo_segment_form.xml — 'all' (AND) is the
 * historical, still-default behavior; 'any' (OR) is the newer option (see
 * Model\Segment::getConditionLogic(), Model\Segment\SegmentMatcher, Model\Segment\SegmentMemberResolver).
 */
class ConditionLogic implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'all', 'label' => __('All conditions (AND)')],
            ['value' => 'any', 'label' => __('Any condition (OR)')],
        ];
    }
}
