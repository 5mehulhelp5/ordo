<?php
declare(strict_types=1);

namespace Ordo\Automation\Cron;

use Magento\Framework\App\ResourceConnection;
use Ordo\Automation\Model\Cron\CronRunLogger;

/**
 * Deletes persistent notifications that are no longer relevant: already read (kept briefly for
 * debugging/observability, same reasoning as PrunePendingPopups keeping delivered rows) or
 * expired without ever being read. Same enforcement role as PrunePendingPopups/
 * PruneVisitorEvents, for the same reason: ordo_notification is meant to be a bounded queue,
 * not an ever-growing log.
 */
class PruneNotifications
{
    private const int READ_GRACE_HOURS = 24;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CronRunLogger $cronRunLogger
    ) {
    }

    public function execute(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_notification');
        $now = date('Y-m-d H:i:s');
        $readCutoff = date('Y-m-d H:i:s', strtotime('-' . self::READ_GRACE_HOURS . ' hours'));

        $deletedRead = $connection->delete($table, [
            'read_at IS NOT NULL',
            'read_at < ?' => $readCutoff,
        ]);
        $deletedExpired = $connection->delete($table, [
            'read_at IS NULL',
            'expires_at IS NOT NULL',
            'expires_at < ?' => $now,
        ]);

        $this->cronRunLogger->logSummary(sprintf(
            'pruned %d read and %d expired-unread notifications',
            $deletedRead,
            $deletedExpired
        ));
    }
}
