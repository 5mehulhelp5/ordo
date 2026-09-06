<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Config\Source;

use Ordo\Automation\Model\Config\Source\LoyaltyTier;
use Ordo\Automation\Model\LoyaltyTierCalculator;
use PHPUnit\Framework\TestCase;

class LoyaltyTierTest extends TestCase
{
    public function testToOptionArrayReturnsAllTiersInAscendingOrder(): void
    {
        $loyaltyTierCalculator = $this->createStub(LoyaltyTierCalculator::class);
        $loyaltyTierCalculator->method('getAllTiers')->willReturn(['bronze', 'silver', 'gold']);
        $loyaltyTierCalculator->method('getTierLabel')->willReturnMap([
            ['bronze', 'Bronze'],
            ['silver', 'Silver'],
            ['gold', 'Gold'],
        ]);

        $options = (new LoyaltyTier($loyaltyTierCalculator))->toOptionArray();

        self::assertSame(
            [
                ['value' => 'bronze', 'label' => 'Bronze'],
                ['value' => 'silver', 'label' => 'Silver'],
                ['value' => 'gold', 'label' => 'Gold'],
            ],
            $options
        );
    }
}
