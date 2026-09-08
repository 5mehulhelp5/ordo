<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Config\Source;

use Ordo\Automation\Model\Config\Source\ConditionType;
use Ordo\Automation\Model\Config\Source\ConditionTypeWithGroup;
use PHPUnit\Framework\TestCase;

class ConditionTypeWithGroupTest extends TestCase
{
    public function testToOptionArrayAppendsTheSyntheticGroupOptionAfterConditionTypesOwnList(): void
    {
        $conditionType = $this->createStub(ConditionType::class);
        $conditionType->method('toOptionArray')->willReturn([
            ['value' => 'tag', 'label' => 'Has Tag'],
            ['value' => 'order_total_gte', 'label' => 'Order Total ≥'],
        ]);

        $options = (new ConditionTypeWithGroup($conditionType))->toOptionArray();

        self::assertCount(3, $options);
        self::assertSame('tag', $options[0]['value']);
        self::assertSame('order_total_gte', $options[1]['value']);
        self::assertSame('group', $options[2]['value']);
        self::assertSame('Group (nested AND/OR)', (string) $options[2]['label']);
    }

    public function testToOptionArrayStillAppendsGroupWhenConditionTypeIsEmpty(): void
    {
        $conditionType = $this->createStub(ConditionType::class);
        $conditionType->method('toOptionArray')->willReturn([]);

        $options = (new ConditionTypeWithGroup($conditionType))->toOptionArray();

        self::assertSame([['value' => 'group', 'label' => 'Group (nested AND/OR)']], [
            ['value' => $options[0]['value'], 'label' => (string) $options[0]['label']],
        ]);
    }
}
