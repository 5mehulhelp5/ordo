<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model;

use Ordo\Automation\Model\Segment;

class SegmentTest extends AbstractModelTestCase
{
    private function makeModel(): Segment
    {
        return new Segment($this->makeModelContext(), $this->makeRegistry(), $this->makeModelResource());
    }

    public function testEntityIdRoundTrip(): void
    {
        $model = $this->makeModel();
        self::assertNull($model->getEntityId());

        $model->setData('entity_id', '5');
        self::assertSame(5, $model->getEntityId());
    }

    public function testNameRoundTrip(): void
    {
        $model = $this->makeModel();
        $model->setName('VIP customers');
        self::assertSame('VIP customers', $model->getName());
    }

    public function testEnabledRoundTrip(): void
    {
        $model = $this->makeModel();
        self::assertFalse($model->isEnabled());

        $model->setEnabled(true);
        self::assertTrue($model->isEnabled());
    }

    public function testConditionLogicDefaultsToAllWhenNeverSet(): void
    {
        self::assertSame('all', $this->makeModel()->getConditionLogic());
    }

    public function testConditionLogicRoundTripsAny(): void
    {
        $model = $this->makeModel();
        $model->setConditionLogic('any');

        self::assertSame('any', $model->getConditionLogic());
    }

    public function testConditionLogicNormalizesAnyOtherValueToAll(): void
    {
        $model = $this->makeModel();
        $model->setConditionLogic('bogus');

        self::assertSame('all', $model->getConditionLogic());
    }
}
