<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;
use PDO;

/**
 * Direct-DB read access to ordo_push_subscription (keyed by endpoint_hash = sha256(endpoint),
 * the same lookup PushSubscriptionManager itself uses - see that table's own db_schema.xml
 * comment for why endpoint, a text column, isn't queried directly) for
 * AdminPushSubscriptionLifecycleTest/AdminPushSubscriptionLoggedInOriginCheckTest - proving a
 * rejected registration produced no row, and an accepted one produced the real customer_id/
 * visitor_id/key values it should have.
 */
class PushSubscriptionTestHelper extends Helper
{
    public function countSubscriptionsForEndpoint(
        string $endpoint,
        string $dbHost = '127.0.0.1',
        string $dbName = 'magento',
        string $dbUser = 'root',
        string $dbPassword = ''
    ): int {
        $pdo = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM ordo_push_subscription WHERE endpoint_hash = :endpoint_hash'
        );
        $statement->execute(['endpoint_hash' => hash('sha256', $endpoint)]);

        return (int) $statement->fetchColumn();
    }

    public function getCustomerIdForEndpoint(
        string $endpoint,
        string $dbHost = '127.0.0.1',
        string $dbName = 'magento',
        string $dbUser = 'root',
        string $dbPassword = ''
    ): string {
        $pdo = new PDO(
            "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $statement = $pdo->prepare(
            'SELECT customer_id FROM ordo_push_subscription WHERE endpoint_hash = :endpoint_hash'
        );
        $statement->execute(['endpoint_hash' => hash('sha256', $endpoint)]);

        return (string) $statement->fetchColumn();
    }
}
