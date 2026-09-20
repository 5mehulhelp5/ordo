<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel\MessageLog\Grid;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\ObjectManagerInterface;
use Ordo\Automation\Model\ResourceModel\MessageLog\Grid\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Same ObjectManager-singleton-stubbing technique Campaign\Grid\CollectionTest already
 * establishes for a SearchResult-based grid collection - see that test's own docblock for why.
 */
class CollectionTest extends TestCase
{
    protected function tearDown(): void
    {
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testInitSelectLeftJoinsCustomerNameAndEmail(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->expects(self::once())->method('joinLeft')->with(
            ['customer' => 'customer_entity'],
            'customer.entity_id = main_table.customer_id',
            self::callback(fn (array $cols) => isset($cols['customer_name'], $cols['customer_email'])
                && (string) $cols['customer_name'] === "CONCAT(customer.firstname, ' ', customer.lastname)"
                && $cols['customer_email'] === 'customer.email')
        )->willReturnSelf();

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);

        $resource = $this->createStub(AbstractDb::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('ordo_message_log');
        $resource->method('getIdFieldName')->willReturn('entity_id');
        $resource->method('getTable')->willReturnCallback(fn (string $table) => $table);

        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnCallback(
            fn ($table) => is_string($table) ? $table : 'ordo_message_log'
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
    }

    /**
     * Same ObjectManager stubbing as the test above, extracted since addFieldToFilter() coverage
     * below needs a real, fully-constructed Collection (not just to observe _initSelect()'s own
     * joinLeft() call) to invoke the method under test against.
     */
    private function buildCollection(AdapterInterface $connection): Collection
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnCallback(
            static fn ($table) => is_string($table) ? $table : 'ordo_message_log'
        );

        $resourceModel = $this->createStub(AbstractDb::class);
        $resourceModel->method('getConnection')->willReturn($connection);
        $resourceModel->method('getIdFieldName')->willReturn('entity_id');

        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($resourceConnection);
        $objectManager->method('create')->willReturn($resourceModel);
        ObjectManager::setInstance($objectManager);

        $collection = new Collection(
            $this->createStub(EntityFactoryInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(FetchStrategyInterface::class),
            $this->createStub(ManagerInterface::class),
            $resourceConnection
        );
        // _initSelect() runs lazily on first getSelect().
        $collection->getSelect();

        return $collection;
    }

    public function testAddFieldToFilterCustomerNameUsesPrepareSqlConditionOnRawExpression(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();

        $whereArgs = null;
        $select->expects(self::once())->method('where')->willReturnCallback(
            function (...$args) use (&$whereArgs, $select) {
                $whereArgs = $args;
                return $select;
            }
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('prepareSqlCondition')
            ->with("CONCAT(customer.firstname, ' ', customer.lastname)", ['like' => '%Jane%'])
            ->willReturn("CONCAT(customer.firstname, ' ', customer.lastname) LIKE '%Jane%'");

        $collection = $this->buildCollection($connection);

        $result = $collection->addFieldToFilter('customer_name', ['like' => '%Jane%']);

        self::assertSame($collection, $result);
        // The mocked where() reports its full 3-parameter signature (value/type filled with
        // their real defaults) even though the override itself calls where() with one argument.
        self::assertSame(
            ["CONCAT(customer.firstname, ' ', customer.lastname) LIKE '%Jane%'", null, null],
            $whereArgs
        );
    }

    public function testAddFieldToFilterEntityIdUsesMappedMainTableColumn(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();

        $whereArgs = null;
        $select->expects(self::once())->method('where')->willReturnCallback(
            function (...$args) use (&$whereArgs, $select) {
                $whereArgs = $args;
                return $select;
            }
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->expects(self::once())->method('prepareSqlCondition')
            ->with('main_table.entity_id', ['eq' => 5])
            ->willReturn('main_table.entity_id = 5');

        $collection = $this->buildCollection($connection);

        $result = $collection->addFieldToFilter('entity_id', ['eq' => 5]);

        self::assertSame($collection, $result);
        self::assertSame(['main_table.entity_id = 5', null, Select::TYPE_CONDITION], $whereArgs);
    }

    public function testAddFieldToFilterOtherFieldDelegatesToParent(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();

        $whereArgs = null;
        $select->expects(self::once())->method('where')->willReturnCallback(
            function (...$args) use (&$whereArgs, $select) {
                $whereArgs = $args;
                return $select;
            }
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->expects(self::once())->method('prepareSqlCondition')
            ->with('customer_email', ['eq' => 'a@example.com'])
            ->willReturn("customer_email = 'a@example.com'");

        $collection = $this->buildCollection($connection);

        $result = $collection->addFieldToFilter('customer_email', ['eq' => 'a@example.com']);

        self::assertSame($collection, $result);
        self::assertSame(["customer_email = 'a@example.com'", null, Select::TYPE_CONDITION], $whereArgs);
    }
}
