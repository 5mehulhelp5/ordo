<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Ordo\Automation\Model\LoyaltyTierCalculator;

class LoyaltyTier implements OptionSourceInterface
{
    public function __construct(
        private readonly LoyaltyTierCalculator $loyaltyTierCalculator
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->loyaltyTierCalculator->getAllTiers() as $tier) {
            $options[] = ['value' => $tier, 'label' => $this->loyaltyTierCalculator->getTierLabel($tier)];
        }

        return $options;
    }
}
