<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * Confirms Controller\Adminhtml\Gdpr\Erase's real, database-observable effect — no MFTF-reachable
 * assertion on the admin page itself proves a *deletion* happened (the page just redirects back
 * to an empty search form either way), so this reads ordo_customer_consent directly, same
 * reasoning as NotificationTestHelper/SurveyPromptTestHelper for their own tables.
 */
class GdprTestHelper extends Helper
{
    public function assertNoConsentRowForCustomer(
        int $customerId,
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

        $statement = $pdo->prepare('SELECT COUNT(*) FROM ordo_customer_consent WHERE customer_id = :customer_id');
        $statement->execute(['customer_id' => $customerId]);
        $count = (int) $statement->fetchColumn();

        if ($count > 0) {
            throw new \RuntimeException(sprintf(
                'Unexpected ordo_customer_consent row still found for customer_id=%d.',
                $customerId
            ));
        }
    }
}
