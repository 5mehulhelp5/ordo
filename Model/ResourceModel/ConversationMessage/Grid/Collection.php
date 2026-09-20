<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\ConversationMessage\Grid;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Ordo\Automation\Model\ResourceModel\ConversationMessage as ConversationMessageResource;
use Psr\Log\LoggerInterface;
use Zend_Db_Expr;

/**
 * Same SearchResult-based grid collection shape as Model\ResourceModel\MessageLog\Grid\
 * Collection, including the same customer_id -> name/email LEFT JOIN, so the conversation view
 * reads like a real customer-tied conversation instead of a raw phone-number dump.
 */
class Collection extends SearchResult
{
    private const string CUSTOMER_NAME_EXPR = "CONCAT(customer.firstname, ' ', customer.lastname)";

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        private readonly ResourceConnection $resourceConnection,
        $mainTable = 'ordo_conversation_message',
        $resourceModel = ConversationMessageResource::class,
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
     * column 'customer_name' in 'where clause'", error 1054), a real fatal error the Customer
     * filter in this grid's own listingToolbar hit the moment it actually got one (see that
     * element's own docblock - this listing had no visible filter panel at all before). The
     * $_map mechanism other Grid Collections in this module use for a real column's ambiguous
     * name (e.g. Model\ResourceModel\ReorderCycle\Grid\Collection's own entity_id fix) doesn't
     * work here either - it quotes its mapped value as if it were a plain identifier, which
     * mangles a function-call expression like CONCAT(...) into more invalid SQL, confirmed via
     * a real second failed attempt. prepareSqlCondition() against the raw expression directly is
     * the correct tool for a genuinely computed column. Model\ResourceModel\MessageLog\Grid\
     * Collection has the identical join/alias shape and the identical latent bug, fixed the same
     * way in that class.
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
