<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\ReorderCycle\Grid;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Ordo\Automation\Model\ResourceModel\ReorderCycle as ReorderCycleResource;
use Psr\Log\LoggerInterface;
use Zend_Db_Expr;

/**
 * SearchResult takes its table/resource model via constructor arguments (wired in
 * etc/di.xml), not via _init() in _construct() — see Campaign\Grid\Collection.
 *
 * Turns the raw customer_id/SKU diagnostic dump into a real report (ROADMAP.md "Admin UX"
 * Phase 2): resolves the customer's name/email and the product's name — the latter from
 * sales_order_item.name (the item name actually recorded on past orders), not an EAV join,
 * since that's already denormalized and always available — plus whether a reorder reminder
 * for this cycle actually led to a reorder, sourced from ordo_reorder_reminder_log's own
 * "reacted" flag (the same table SendReorderReminders already writes to record exactly this).
 */
class Collection extends SearchResult
{
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        private readonly ResourceConnection $resourceConnection,
        $mainTable = 'ordo_reorder_cycle',
        $resourceModel = ReorderCycleResource::class,
        $identifierName = 'entity_id',
        $connectionName = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    protected function _initSelect(): void
    {
        parent::_initSelect();

        $connection = $this->resourceConnection->getConnection();

        $this->getSelect()->joinLeft(
            ['customer' => $this->resourceConnection->getTableName('customer_entity')],
            'customer.entity_id = main_table.customer_id',
            [
                'customer_name' => new Zend_Db_Expr("CONCAT(customer.firstname, ' ', customer.lastname)"),
                'customer_email' => 'customer.email',
            ]
        );

        $itemNames = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('sales_order_item'),
                ['sku' => 'sku', 'product_name' => 'MAX(name)']
            )
            ->group('sku');

        $this->getSelect()->joinLeft(
            ['item' => new Zend_Db_Expr('(' . $itemNames->assemble() . ')')],
            'item.sku = main_table.sku',
            ['product_name' => 'item.product_name']
        );

        $reminderOutcome = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('ordo_reorder_reminder_log'),
                [
                    'reorder_cycle_id' => 'reorder_cycle_id',
                    'reminders_sent' => 'COUNT(*)',
                    'last_reminder_sent_at' => 'MAX(sent_at)',
                    'reordered' => 'MAX(reacted)',
                ]
            )
            ->group('reorder_cycle_id');

        $this->getSelect()->joinLeft(
            ['reminder' => new Zend_Db_Expr('(' . $reminderOutcome->assemble() . ')')],
            'reminder.reorder_cycle_id = main_table.entity_id',
            [
                'reminders_sent' => new Zend_Db_Expr('COALESCE(reminder.reminders_sent, 0)'),
                'last_reminder_sent_at' => 'reminder.last_reminder_sent_at',
                'reordered' => new Zend_Db_Expr(
                    "CASE WHEN COALESCE(reminder.reordered, 0) = 1 THEN 'Yes' ELSE 'No' END"
                ),
            ]
        );
    }
}
