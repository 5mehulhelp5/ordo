<?php
declare(strict_types=1);

namespace Ordo\Automation\Cron;

use Magento\Framework\App\ResourceConnection;
use Ordo\Automation\Model\Cron\CronRunLogger;

/**
 * Deletes survey prompts that are no longer relevant: already answered (kept briefly for
 * debugging/observability — nothing ever reads a responded row again except
 * NpsScoreAtLeast::addLatestResponseFilter(), which only wants the single most recent one, not an
 * unbounded history) or expired without ever being polled. Same enforcement role as
 * PrunePendingPopups/PruneNotifications, for the same reason: ordo_survey_prompt is meant to be a
 * short-lived queue, not an ever-growing log.
 */
class PruneSurveyPrompts
{
    private const int RESPONDED_GRACE_HOURS = 24;
    private const int DELIVERED_UNANSWERED_GRACE_HOURS = 24;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CronRunLogger $cronRunLogger
    ) {
    }

    public function execute(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_survey_prompt');
        $now = date('Y-m-d H:i:s');
        $respondedCutoff = date('Y-m-d H:i:s', strtotime('-' . self::RESPONDED_GRACE_HOURS . ' hours'));
        $deliveredCutoff = date('Y-m-d H:i:s', strtotime('-' . self::DELIVERED_UNANSWERED_GRACE_HOURS . ' hours'));

        $deletedResponded = $connection->delete($table, [
            'responded_at IS NOT NULL',
            'responded_at < ?' => $respondedCutoff,
        ]);
        $deletedExpired = $connection->delete($table, [
            'responded_at IS NULL',
            'delivered_at IS NULL',
            'expires_at IS NOT NULL',
            'expires_at < ?' => $now,
        ]);
        // Delivered (shown on-screen) but never answered — the visitor isn't coming back to it,
        // same reasoning as PrunePendingPopups.DELIVERED_GRACE_HOURS for a popup nobody clicked.
        $deletedStaleDelivered = $connection->delete($table, [
            'responded_at IS NULL',
            'delivered_at IS NOT NULL',
            'delivered_at < ?' => $deliveredCutoff,
        ]);

        $this->cronRunLogger->logSummary(sprintf(
            'pruned %d responded, %d expired-undelivered and %d stale delivered-unanswered survey prompts',
            $deletedResponded,
            $deletedExpired,
            $deletedStaleDelivered
        ));
    }
}
