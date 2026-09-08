<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel\ReorderCycle\Grid;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\ObjectManagerInterface;
use Ordo\Automation\Model\ResourceModel\ReorderCycle\Grid\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Same ObjectManager-singleton-stubbing technique Campaign\Grid\CollectionTest already
 * establishes for a SearchResult-based grid collection - see that test's own docblock for why.
 * _initSelect() here does three joinLeft() calls (customer name/email, a sales_order_item-derived
 * product name subquery, a reminder-outcome subquery) - each subquery calls $connection->select()
 * again, so the same mocked Select stub is asked to build/assemble a second and third time; a
 * bare stub with from()/group() all returning self and a real (empty-but-callable) assemble()
 * covers all three without needing to distinguish which call is which.
 */
class CollectionTest extends TestCase
{
    protected function tearDown(): void
    {
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testInitSelectJoinsCustomerProductAndReminderOutcomeData(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('group')->willReturnSelf();
        $select->method('assemble')->willReturn('SELECT 1');

        $joins = [];
        $select->method('joinLeft')->willReturnCallback(function ($name, $cond, $cols) use (&$joins, $select) {
            $joins[] = ['name' => $name, 'cond' => $cond, 'cols' => $cols];
            return $select;
        });

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);

        $resource = $this->createStub(AbstractDb::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('ordo_reorder_cycle');
        $resource->method('getIdFieldName')->willReturn('entity_id');
        $resource->method('getTable')->willReturnCallback(fn (string $table) => $table);

        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnCallback(
            fn ($table) => is_string($table) ? $table : 'ordo_reorder_cycle'
        );

        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('create')->willReturn($resource);
        $objectManager->method('get')->willReturnMap([[ResourceConnection::class, $resourceConnection]]);
        ObjectManager::setInstance($objectManager);

        new Collection(
            $this->createStub(EntityFactoryInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(FetchStrategyInterface::class),
            $this->createStub(ManagerInterface::class),
            $resourceConnection
        );

        self::assertCount(3, $joins, 'customer, product-name, and reminder-outcome joins');

        self::assertSame(['customer' => 'customer_entity'], $joins[0]['name']);
        self::assertSame('customer.entity_id = main_table.customer_id', $joins[0]['cond']);
        self::assertArrayHasKey('customer_name', $joins[0]['cols']);
        self::assertArrayHasKey('customer_email', $joins[0]['cols']);

        self::assertSame('item.sku = main_table.sku', $joins[1]['cond']);
        self::assertArrayHasKey('product_name', $joins[1]['cols']);

        self::assertSame('reminder.reorder_cycle_id = main_table.entity_id', $joins[2]['cond']);
        self::assertArrayHasKey('reminders_sent', $joins[2]['cols']);
        self::assertArrayHasKey('last_reminder_sent_at', $joins[2]['cols']);
        self::assertArrayHasKey('reordered', $joins[2]['cols']);
    }
}
