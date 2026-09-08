<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Segment;

use Ordo\Automation\Model\Campaign\ConditionPool;
use Ordo\Automation\Model\ResourceModel\Segment as SegmentResource;
use Ordo\Automation\Model\ResourceModel\Segment\Condition\CollectionFactory as SegmentConditionCollectionFactory;
use Ordo\Automation\Model\SegmentFactory;
use Psr\Log\LoggerInterface;

/**
 * Evaluates a saved segment's conditions against a customer, reusing the exact same
 * ConditionPool/fail-closed rules CampaignDispatcher uses for a campaign's own conditions (see
 * CampaignDispatcher::allConditionsSatisfied) — a segment IS just a named, reusable set of
 * campaign conditions, so it should behave identically. Combines them with AND or OR depending
 * on the segment's own condition_logic (Model\Segment::getConditionLogic()) — historically always
 * AND, "any" (OR) is the newer option (see ordo_segment_form.xml's ALL/ANY toggle).
 *
 * A segment with zero conditions never matches anyone regardless of condition_logic — an empty
 * AND is vacuously true and an empty OR is vacuously false in boolean logic, but "matches every
 * customer" is almost certainly not what an admin who forgot to add conditions to a new segment
 * intended, so this deliberately fails closed instead either way.
 */
class SegmentMatcher
{
    public function __construct(
        private readonly SegmentConditionCollectionFactory $segmentConditionCollectionFactory,
        private readonly ConditionPool $conditionPool,
        private readonly SegmentFactory $segmentFactory,
        private readonly SegmentResource $segmentResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int[] $visitedSegmentIds segment IDs already being evaluated in this call chain —
     *  threaded through via the '_in_segment_visited' context key so a nested in_segment
     *  condition (Model\Campaign\Condition\InSegment) can guard against cycles (segment A
     *  references B references A) the same way Model\Segment\SegmentMemberResolver does for its
     *  set-level resolve. Without this, a cyclic segment graph recurses until the call stack
     *  overflows instead of failing closed.
     */
    public function isCustomerInSegment(int $segmentId, int $customerId, array $visitedSegmentIds = []): bool
    {
        if (in_array($segmentId, $visitedSegmentIds, true)) {
            return false;
        }

        $visitedSegmentIds[] = $segmentId;

        $conditions = $this->segmentConditionCollectionFactory->create();
        $conditions->addSegmentFilter($segmentId);

        if ($conditions->getSize() === 0) {
            return false;
        }

        $context = ['customer_id' => $customerId, '_in_segment_visited' => $visitedSegmentIds];

        $segment = $this->segmentFactory->create();
        $this->segmentResource->load($segment, $segmentId);
        $matchAny = $segment->getConditionLogic() === 'any';

        foreach ($conditions as $conditionRow) {
            $type = (string) $conditionRow->getData('type');
            $condition = $this->conditionPool->get($type);

            if (!$condition instanceof \Ordo\Automation\Api\Campaign\ConditionInterface) {
                $this->logger->error(sprintf('Ordo_Automation: unknown segment condition type "%s".', $type));
                return false;
            }

            $satisfied = $condition->isSatisfied($context, $conditionRow->getParams());

            if ($matchAny && $satisfied) {
                return true;
            }

            if (!$matchAny && !$satisfied) {
                return false;
            }
        }

        // Loop finished without an early return: under AND every condition passed (all still
        // true), under OR none of them did.
        return !$matchAny;
    }
}
