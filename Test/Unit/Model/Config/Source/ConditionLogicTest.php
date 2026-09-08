<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Config\Source;

use Ordo\Automation\Model\Config\Source\ConditionLogic;
use PHPUnit\Framework\TestCase;

class ConditionLogicTest extends TestCase
{
    public function testToOptionArrayListsAllThenAny(): void
    {
        $options = (new ConditionLogic())->toOptionArray();

        self::assertCount(2, $options);
        self::assertSame('all', $options[0]['value']);
        self::assertSame('All conditions (AND)', (string) $options[0]['label']);
        self::assertSame('any', $options[1]['value']);
        self::assertSame('Any condition (OR)', (string) $options[1]['label']);
    }
}
