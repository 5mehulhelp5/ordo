<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Campaign\Condition;

use Ordo\Automation\Api\Campaign\ConditionInterface;
use Ordo\Automation\Model\CustomerScoreManager;
use Ordo\Automation\Model\LoyaltyTierCalculator;

/**
 * Params: {"tier": "gold"}. Context must include "customer_id". Reads the same running
 * ordo_customer_score total ScoreAtLeast already reads (via CustomerScoreManager::getScore()) -
 * this is a pure re-labeling of that score into a named tier, not a second scoring system, so
 * a customer earning points via any existing mechanism (add_points action, demographic score
 * rules) moves between tiers automatically with no new bookkeeping.
 */
class LoyaltyTierAtLeast implements ConditionInterface
{
    public function __construct(
        private readonly CustomerScoreManager $customerScoreManager,
        private readonly LoyaltyTierCalculator $loyaltyTierCalculator
    ) {
    }

    public function isSatisfied(array $context, array $params): bool
    {
        $customerId = (int) ($context['customer_id'] ?? 0);
        $tier = $params['tier'] ?? null;

        if ($customerId <= 0 || !is_string($tier) || $tier === '') {
            return false;
        }

        $score = $this->customerScoreManager->getScore($customerId);

        return $this->loyaltyTierCalculator->isAtLeast($score, $tier);
    }
}
