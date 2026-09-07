<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\Campaign;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Ordo\Automation\Model\CampaignScheduledAction;

class ScheduledAction extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('ordo_campaign_scheduled_action', 'entity_id');
    }

    /**
     * Atomically claims a due row before resuming it — a plain load()-then-save() is a
     * read-then-write race: two overlapping cron runs (a manual bin/magento cron:run while the
     * scheduled cron is still mid-batch, or one tick overrunning into the next) can both load the
     * same row before either writes executed_at, and both then dispatch the action a second time.
     * This does the claim as a single conditional UPDATE ... WHERE executed_at IS NULL, so only
     * whichever process's UPDATE actually matches a row (affected rows > 0) may proceed; the loser
     * sees 0 affected rows and must skip the row instead of resuming it.
     */
    public function claim(CampaignScheduledAction $model, string $now): bool
    {
        $connection = $this->getConnection();

        $affectedRows = $connection->update(
            $this->getMainTable(),
            ['executed_at' => $now],
            $connection->quoteInto('entity_id = ?', (int) $model->getEntityId()) . ' AND executed_at IS NULL'
        );

        if ($affectedRows > 0) {
            $model->setExecutedAt($now);
        }

        return $affectedRows > 0;
    }
}
