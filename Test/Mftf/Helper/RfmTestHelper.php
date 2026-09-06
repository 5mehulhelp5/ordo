<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * Inserts a throwaway row into ordo_customer_rfm_score for a customer_id that will never
 * actually exist, so the table is non-empty (RfmCalculator::readStoredPercentileRanks()
 * returns real, if incomplete, data instead of null) before Cron\RecomputeRfmScores has ever
 * run for the customer this test actually cares about. This is what makes an MFTF assertion
 * meaningful proof that a percentile condition reads the *precomputed* table rather than
 * falling back to a live scan: if it read live, the condition would already be satisfied right
 * after the customer's first real order, with no cron involved at all — placing that same
 * order while the table already has *other* data (but not this customer's) is what actually
 * exercises the "not found in the stored ranks, condition fails closed" path first.
 */
class RfmTestHelper extends Helper
{
    private const int POISON_CUSTOMER_ID = 999999999;

    public function insertPoisonRfmScoreRow(
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
            'REPLACE INTO ordo_customer_rfm_score '
            . '(customer_id, recency_percentile, frequency_percentile, monetary_percentile, '
            . 'recency_quintile, frequency_quintile, monetary_quintile) '
            . 'VALUES (:customer_id, 50, 50, 50, 3, 3, 3)'
        );
        $statement->execute(['customer_id' => self::POISON_CUSTOMER_ID]);
    }
}
