<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel\Campaign;

use Ordo\Automation\Model\CampaignScheduledAction;
use Ordo\Automation\Model\ResourceModel\Campaign\ScheduledAction;
use Ordo\Automation\Test\Unit\Model\ResourceModel\AbstractDbTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class ScheduledActionTest extends AbstractDbTestCase
{
    public function testInitializesWithScheduledActionTableAndEntityIdField(): void
    {
        $resource = new ScheduledAction($this->makeDbContext());

        self::assertSame('ordo_campaign_scheduled_action', $resource->getMainTable());
        self::assertSame('entity_id', $resource->getIdFieldName());
    }

    /**
     * Regression test for a real race-condition bug a code audit found: this used to be a plain
     * load()-then-save(), which two overlapping cron runs could both "win" for the same row. The
     * atomic conditional UPDATE (this method) must only report success when it genuinely claimed
     * the row (affected rows > 0), and must set the model's own executedAt to match.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testClaimUpdatesModelAndReturnsTrueWhenRowWasStillUnclaimed(): void
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            fn (string $sql, $value) => str_replace('?', (string) $value, $sql)
        );
        $connection->expects(self::once())->method('update')
            ->with('ordo_campaign_scheduled_action', ['executed_at' => '2026-01-01 00:00:00'], 'entity_id = 5 AND executed_at IS NULL')
            ->willReturn(1);

        $resource = $this->getMockBuilder(ScheduledAction::class)
            ->setConstructorArgs([$this->makeDbContext()])
            ->onlyMethods(['getConnection'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);

        $model = $this->createMock(CampaignScheduledAction::class);
        $model->method('getEntityId')->willReturn(5);
        $model->expects(self::once())->method('setExecutedAt')->with('2026-01-01 00:00:00');

        self::assertTrue($resource->claim($model, '2026-01-01 00:00:00'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testClaimReturnsFalseAndDoesNotTouchTheModelWhenAlreadyClaimed(): void
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            fn (string $sql, $value) => str_replace('?', (string) $value, $sql)
        );
        $connection->method('update')->willReturn(0);

        $resource = $this->getMockBuilder(ScheduledAction::class)
            ->setConstructorArgs([$this->makeDbContext()])
            ->onlyMethods(['getConnection'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);

        $model = $this->createMock(CampaignScheduledAction::class);
        $model->method('getEntityId')->willReturn(5);
        $model->expects(self::never())->method('setExecutedAt');

        self::assertFalse($resource->claim($model, '2026-01-01 00:00:00'));
    }
}
