<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * Inserts a real ordo_message_log row directly via SQL — this table is written only by
 * Model\Sms\MessageLogWriter (from a real send_sms campaign action, which needs a real Twilio
 * account this test environment doesn't have, see ROADMAP.md) or the delivery-status webhook, so
 * there is no MFTF-reachable path that produces a row without one. Same reasoning as
 * OfferTestHelper/OrderBackdateHelper for their own tables.
 */
class MessageLogTestHelper extends Helper
{
    public function insertMessageLog(
        string $channel,
        string $toAddress,
        string $status,
        int $customerId = 0,
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
            'INSERT INTO ordo_message_log (channel, customer_id, to_address, status) '
            . 'VALUES (:channel, :customer_id, :to_address, :status)'
        );
        $statement->execute([
            'channel' => $channel,
            'customer_id' => $customerId > 0 ? $customerId : null,
            'to_address' => $toAddress,
            'status' => $status,
        ]);
    }
}
