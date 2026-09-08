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
                'customer_name' => new Zend_Db_Expr("CONCAT(customer.firstname, ' ', customer.lastname)"),
                'customer_email' => 'customer.email',
            ]
        );
    }
}
