<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * Rewrites a just-created campaign's single action row to an unregistered type — there's no
 * admin UI path to this state (the Action Type <select> only ever lists what
 * Model\Campaign\ActionPool was wired with via di.xml), yet it's a real state a campaign can end
 * up in (a module providing a custom action type gets uninstalled, or di.xml is edited to drop
 * one, while a campaign still references it) — CampaignDispatcher::runOneAction() must fail
 * closed for THAT campaign (log and skip, see the "unknown campaign action type" error) without
 * ever aborting the batch for every OTHER campaign due at the same time. Same direct-DB-write
 * technique as CronScheduleHelper, for the same reason: no MFTF-reachable write path exists here.
 */
class CampaignActionCorruptorHelper extends Helper
{
    /**
     * ORDER BY entity_id ASC — the FIRST action added to the campaign, not the most recent one:
     * a real CI run caught this the hard way. Actions are persisted in sort_order (first added
     * gets the LOWEST entity_id), so an earlier version of this method ordered DESC ("most
     * recent") and actually grabbed the LAST action in the chain instead — silently corrupting
     * AdminCampaignUnknownActionTypeFailsClosedTest's own second, "should still fire" send_email
     * action instead of the first add_tag one, the exact opposite of the scenario it's meant to
     * prove, which is why its own email assertion then failed for real.
     */
    public function corruptFirstActionType(
        string $campaignId,
        string $bogusType = 'no_such_action_type',
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
            'UPDATE ordo_campaign_action SET type = :type '
            . 'WHERE campaign_id = :campaign_id ORDER BY entity_id ASC LIMIT 1'
        );
        $statement->execute(['type' => $bogusType, 'campaign_id' => $campaignId]);
    }
}
