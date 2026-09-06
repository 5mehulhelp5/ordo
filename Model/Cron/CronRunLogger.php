<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Cron;

use Psr\Log\LoggerInterface;

/**
 * Wraps the "Ordo_Automation: failed to ...: %s" / "Ordo_Automation: ... ." log-line shape
 * duplicated across the reminder/alert crons (SendWinBackEmails, SendOfferExpiryReminders,
 * SendReorderReminders, SendCreditLimitAlerts, SendSalesRepDigest). Deliberately doesn't wrap the
 * try/catch itself: the per-item failure handling (skip vs. abort, what counts as "sent") is real
 * cron-specific logic, only the log-line formatting was boilerplate.
 */
class CronRunLogger
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string $action Present-tense description of the failed action, e.g.
     *   "send win-back email to customer #5" — the "Ordo_Automation: failed to " prefix and the
     *   exception message are added here.
     */
    public function logFailure(string $action, \Throwable $e): void
    {
        $this->logger->error(sprintf('Ordo_Automation: failed to %s: %s', $action, $e->getMessage()));
    }

    /**
     * @param string $summary Past-tense summary of the run, e.g. "sent 3 win-back emails" — the
     *   "Ordo_Automation: " prefix and trailing period are added here.
     */
    public function logSummary(string $summary): void
    {
        $this->logger->info(sprintf('Ordo_Automation: %s.', $summary));
    }
}
