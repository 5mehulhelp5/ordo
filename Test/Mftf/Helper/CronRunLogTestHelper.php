<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * ordo_cron_run_log has no MFTF-reachable path to produce a distinctively-identifiable, already-
 * old row (every real cron run's own CronRunLogger::logSummary() text varies by real counts and
 * timestamps it), same reasoning as OfferTestHelper/RssCacheTestHelper for their own tables -
 * this inserts one directly, already past Cron\PruneCronRunLog's 30-day retention window, with a
 * distinctive message this suite's own tests won't otherwise produce.
 */
class CronRunLogTestHelper extends Helper
{
    public function insertBackdatedRow(
        string $message,
        string $dbHost = '127.0.0.1',
        string $dbName = 'magento',
        string $dbUser = 'root',
        string $dbPassword = ''
    ): void {
        $pdo = new \PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPassword,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $statement = $pdo->prepare(
            "INSERT INTO ordo_cron_run_log (level, message, created_at) "
            . "VALUES ('summary', :message, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 31 DAY))"
        );
        $statement->execute(['message' => $message]);
    }

    /**
     * @throws \RuntimeException if a matching row still exists
     */
    public function assertNoRowWithMessage(
        string $message,
        string $dbHost = '127.0.0.1',
        string $dbName = 'magento',
        string $dbUser = 'root',
        string $dbPassword = ''
    ): void {
        $pdo = new \PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPassword,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $statement = $pdo->prepare('SELECT COUNT(*) FROM ordo_cron_run_log WHERE message = :message');
        $statement->execute(['message' => $message]);
        $count = (int) $statement->fetchColumn();

        if ($count > 0) {
            throw new \RuntimeException(sprintf(
                'Unexpected ordo_cron_run_log row still found with message "%s".',
                $message
            ));
        }
    }
}
