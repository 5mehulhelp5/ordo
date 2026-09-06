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

    /**
     * Backdates the most recently written ordo_campaign_scheduled_action row's run_at into the
     * past, so Cron\RunScheduledCampaignActions' own addDueFilter() (run_at <= NOW()) finds it
     * as due immediately - forcing the CRON JOB to run via scheduleJobNow() alone isn't enough
     * here, unlike every other cron this helper targets: this one cron doesn't just check
     * "is it my turn on the clock", it separately checks whether the specific DATA row it reads
     * is due, which CampaignDispatcher wrote with a real run_at = NOW() + delay_minutes at
     * dispatch time. Safe to key off "most recent row" because MFTF tests run sequentially in
     * one browser session against one Magento install - by the time this is called, the row
     * this same test's own dispatch just wrote is unambiguously the latest one.
     */
    public function backdateMostRecentScheduledAction(
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

        $pdo->exec(
            'UPDATE ordo_campaign_scheduled_action SET run_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) '
            . 'ORDER BY entity_id DESC LIMIT 1'
        );
    }
}
