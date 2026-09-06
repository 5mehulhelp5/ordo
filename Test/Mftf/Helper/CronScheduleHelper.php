<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * Forces a specific cron job to run on the next `cron:run` regardless of its own configured
 * schedule (etc/crontab.xml) — needed for the reminder/alert crons (SendCreditLimitAlerts,
 * SendSalesRepDigest, SendWinBackEmails, TagInactiveCustomers), which only fire once a day (or,
 * for SendSalesRepDigest, once a week) at a specific wall-clock time. `cron:run` only *generates*
 * new `cron_schedule` rows from each job's cron_expr; once a row already exists with
 * status='pending' and a past scheduled_at, Magento's ProcessCronQueueObserver::shouldRunJob()
 * runs it unconditionally — it never re-validates the job's cron_expr at execution time (see
 * vendor/magento/module-cron/Observer/ProcessCronQueueObserver.php). Inserting that row directly
 * is the same technique AdminCampaignDelayedActionTest uses for
 * ordo_campaign_scheduled_action (a different table, same idea: write the "this is due" row
 * directly instead of waiting for real wall-clock time to reach it) — the only difference is
 * Magento's OWN cron_schedule table has no MFTF-reachable write path (no admin UI, no REST
 * endpoint), so this connects to the test DB directly via PDO using the same credentials
 * mftf.yml's `setup:install` step configures (127.0.0.1:3306, database "magento", user "root",
 * empty password by default in CI's mysql service).
 */
class CronScheduleHelper extends Helper
{
    public function scheduleJobNow(
        string $jobCode,
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
            'INSERT INTO cron_schedule (job_code, status, created_at, scheduled_at) '
            . "VALUES (:job_code, 'pending', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        $statement->execute(['job_code' => $jobCode]);
    }
}
