<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Select;
use Ordo\Automation\Model\OrderApproval as OrderApprovalModel;
use Ordo\Automation\Model\ResourceModel\OrderApproval;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class OrderApprovalTest extends AbstractDbTestCase
{
    public function testInitializesWithOrderApprovalTableAndEntityIdField(): void
    {
        $resource = new OrderApproval($this->makeDbContext());

        self::assertSame('ordo_order_approval', $resource->getMainTable());
        self::assertSame('entity_id', $resource->getIdFieldName());
    }

    public function testLoadByTokenDoesNothingWhenTokenNotFound(): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createStub(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn(false);

        $resource = $this->getMockBuilder(OrderApproval::class)
            ->setConstructorArgs([$this->makeDbContext()])
            ->onlyMethods(['getConnection', 'load'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->expects(self::never())->method('load');

        $model = $this->createStub(\Ordo\Automation\Model\OrderApproval::class);
        $resource->loadByToken($model, 'no-such-token');
    }

    public function testLoadByTokenLoadsModelWhenTokenFound(): void
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createStub(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn('7');

        $resource = $this->getMockBuilder(OrderApproval::class)
            ->setConstructorArgs([$this->makeDbContext()])
            ->onlyMethods(['getConnection', 'load'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);

        $model = $this->createStub(\Ordo\Automation\Model\OrderApproval::class);
        $resource->expects(self::once())->method('load')->with($model, 7);

        $resource->loadByToken($model, 'real-token');
    }

    /**
     * Regression test for a real race-condition bug a code audit found: approveByToken()/
     * rejectByToken() used to load-then-save without a lock. This atomic conditional UPDATE must
     * only report success when it genuinely won the transition (affected rows > 0), and must
     * update the model's own status/decided_at to match.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testClaimPendingUpdatesModelAndReturnsTrueWhenStillPending(): void
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            fn (string $sql, $value) => str_replace('?', (string) $value, $sql)
        );
        $connection->method('quote')->willReturnCallback(fn ($value) => "'" . $value . "'");
        $connection->expects(self::once())->method('update')
            ->with(
                'ordo_order_approval',
                self::callback(fn (array $bind) => $bind['status'] === OrderApprovalModel::STATUS_APPROVED
                    && isset($bind['decided_at'])),
                "entity_id = 7 AND status = 'pending'"
            )
            ->willReturn(1);

        $resource = $this->getMockBuilder(OrderApproval::class)
            ->setConstructorArgs([$this->makeDbContext()])
            ->onlyMethods(['getConnection'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);

        $model = $this->createMock(OrderApprovalModel::class);
        $model->method('getEntityId')->willReturn(7);
        $model->expects(self::exactly(2))->method('setData');

        self::assertTrue($resource->claimPending($model, OrderApprovalModel::STATUS_APPROVED));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testClaimPendingReturnsFalseAndDoesNotTouchTheModelWhenAlreadyDecided(): void
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            fn (string $sql, $value) => str_replace('?', (string) $value, $sql)
        );
        $connection->method('quote')->willReturnCallback(fn ($value) => "'" . $value . "'");
        $connection->method('update')->willReturn(0);

        $resource = $this->getMockBuilder(OrderApproval::class)
            ->setConstructorArgs([$this->makeDbContext()])
            ->onlyMethods(['getConnection'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);

        $model = $this->createMock(OrderApprovalModel::class);
        $model->method('getEntityId')->willReturn(7);
        $model->expects(self::never())->method('setData');

        self::assertFalse($resource->claimPending($model, OrderApprovalModel::STATUS_REJECTED));
    }
}
