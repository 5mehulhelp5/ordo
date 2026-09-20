<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\MessageLog\Grid;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Ordo\Automation\Model\ResourceModel\MessageLog as MessageLogResource;
use Psr\Log\LoggerInterface;
use Zend_Db_Expr;

/**
 * Standard admin grid collection — see Model\ResourceModel\Campaign\Grid\Collection for why
 * this is SearchResult-based rather than the plain AbstractCollection the rest of the module
 * uses.
 *
 * Resolves customer_id to a real name/email so the delivery log reads like a support tool a
 * non-technical admin can use, not a raw id dump (ROADMAP.md "Admin UX" Phase 2). customer_id
 * is nullable — a message can be sent to a non-customer contact — so this is a LEFT JOIN.
 */
class Collection extends SearchResult
{
    /**
     * Disambiguates "entity_id" for addFieldToFilter()/addFieldToSelect() callers - both
     * main_table (ordo_message_log) and the customer_entity join below have their own
     * entity_id column, so an unqualified WHERE entity_id = ... is rejected by MySQL as
     * ambiguous (error 1052). Same real fatal error, same fix, as
     * Model\ResourceModel\ReorderCycle\Grid\Collection's own $_map - see that class's docblock;
     * the "Delete" mass action (Controller\Adminhtml\MessageLog\MassDelete) hits this the same
     * way via Magento\Ui\Component\MassAction\Filter::getCollection().
     *
     * "customer_name" (see addFieldToFilter() below) has the identical problem for a different
     * reason - it's a computed SELECT-list alias (CONCAT(...) below), not a real column, and
     * $_map itself doesn't work for it (see that method's own docblock for why).
     *
     * @var array<string, array<string, string>>
     */
    protected $_map = ['fields' => ['entity_id' => 'main_table.entity_id']];

    private const string CUSTOMER_NAME_EXPR = "CONCAT(customer.firstname, ' ', customer.lastname)";

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        private readonly ResourceConnection $resourceConnection,
        $mainTable = 'ordo_message_log',
        $resourceModel = MessageLogResource::class,
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

        $this->getSelect()->joinLeft(
            ['customer' => $this->resourceConnection->getTableName('customer_entity')],
            'customer.entity_id = main_table.customer_id',
            [
                'customer_name' => new Zend_Db_Expr(self::CUSTOMER_NAME_EXPR),
                'customer_email' => 'customer.email',
            ]
        );
    }

    /**
     * "customer_name" is a computed SELECT-list alias (see _initSelect() above), not a real
     * column - MySQL rejects a WHERE clause referencing a SELECT alias directly ("Unknown
     * column 'customer_name' in 'where clause'", error 1054), confirmed for real via
     * Model\ResourceModel\ConversationMessage\Grid\Collection's own identical join/alias shape
     * (see that class's own docblock). $_map (used above for entity_id) doesn't work here
     * either - it quotes its mapped value as if it were a plain identifier, which mangles a
     * function-call expression like CONCAT(...) into more invalid SQL, confirmed via a real
     * second failed attempt. prepareSqlCondition() against the raw expression directly is the
     * correct tool for a genuinely computed column.
     *
     * @param string|array<int|string, mixed> $field
     * @param string|array<string, mixed>|null $condition
     * @return $this
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if ($field === 'customer_name' && $condition !== null) {
            $this->getSelect()->where(
                $this->getConnection()->prepareSqlCondition(self::CUSTOMER_NAME_EXPR, $condition)
            );

            return $this;
        }

        return parent::addFieldToFilter($field, $condition);
    }
}
