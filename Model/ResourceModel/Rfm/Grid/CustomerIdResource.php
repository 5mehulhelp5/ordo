<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\Rfm\Grid;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Exists purely to give Collection (the RFM report grid's own backing collection, which has no
 * Ordo resource model of its own - see this collection's own class doc and etc/di.xml's comment
 * on the same) a real getIdFieldName() to satisfy Magento\Ui\Component\MassAction\Filter::
 * getCollection(), which calls $collection->getResource()->getIdFieldName() directly rather than
 * the collection's own already-correctly-set identifier name.
 *
 * Without this, SearchResult::getResource() falls back to a bare ResourceConnection (no
 * getIdFieldName() at all) whenever a null resourceModel is passed - exactly what this
 * collection did before the "Reset Cached Score" mass action (Controller\Adminhtml\Rfm\
 * MassDelete) was added, which is why that action fatals with "Call to undefined method
 * ResourceConnection::getIdFieldName()" the moment a real admin selects a row and applies it.
 *
 * Never used to write - MassDelete reads the selected customer ids off the collection and hands
 * them to RfmCalculator::resetScoresForCustomers() separately, which operates on
 * ordo_customer_rfm_score, not customer_entity. entity_id/customer_entity is Magento's own real
 * primary key/table (matching Model\ResourceModel\Rfm\Grid\Collection's own mainTable/
 * identifierName), not a made-up one.
 */
class CustomerIdResource extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('customer_entity', 'entity_id');
    }
}
