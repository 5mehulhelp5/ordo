<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OrderApproval extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('ordo_order_approval', 'entity_id');
    }

    public function loadByToken(\Ordo\Automation\Model\OrderApproval $model, string $token): void
    {
        $connection = $this->getConnection();
        $entityId = $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), 'entity_id')
                ->where('token = ?', $token)
        );

        if ($entityId) {
            $this->load($model, (int) $entityId);
        }
    }

    /**
     * Atomically transitions a pending approval to its decided status - a plain load()-then-save()
     * lets two concurrent requests for the same token (a double click, or a forwarded email opened
     * twice) both pass the "still pending" check before either writes, so one order could be both
     * approved and rejected with a silent last-write-wins on status. This does the transition as a
     * single conditional UPDATE ... WHERE status = 'pending', so only whichever request's UPDATE
     * actually matches a row may proceed to touch the order; the loser sees 0 affected rows.
     */
    public function claimPending(\Ordo\Automation\Model\OrderApproval $model, string $status): bool
    {
        $connection = $this->getConnection();
        $decidedAt = date('Y-m-d H:i:s');

        $affectedRows = $connection->update(
            $this->getMainTable(),
            ['status' => $status, 'decided_at' => $decidedAt],
            $connection->quoteInto('entity_id = ?', (int) $model->getEntityId())
            . ' AND status = ' . $connection->quote(\Ordo\Automation\Model\OrderApproval::STATUS_PENDING)
        );

        if ($affectedRows > 0) {
            $model->setData('status', $status);
            $model->setData('decided_at', $decidedAt);
        }

        return $affectedRows > 0;
    }
}
