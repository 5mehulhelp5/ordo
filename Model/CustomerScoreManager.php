<?php
declare(strict_types=1);

namespace Ordo\Automation\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Lead scoring, kept as intentionally dumb as CustomerTagManager: one running points total per
 * customer, no separate ledger of individual point-earning events (that history lives in
 * ordo_campaign_log / whatever action/trigger awarded the points, not here — this table only
 * ever holds the current balance). addPoints() upserts via INSERT ... ON DUPLICATE KEY UPDATE
 * so concurrent awards to the same customer accumulate correctly instead of racing on a
 * read-then-write.
 */
class CustomerScoreManager
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function addPoints(int $customerId, int $points): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_customer_score');

        // ON DUPLICATE KEY UPDATE has no equivalent in Magento's query builder API; parameters
        // are still bound below, not interpolated, and the table name comes from
        // getTableName()/quoteIdentifier(), never from user input.
        $connection->query(
            // phpcs:ignore Magento2.SQL.RawQuery.FoundRawSql
            'INSERT INTO ' . $connection->quoteIdentifier($table) . ' (customer_id, score) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE score = score + VALUES(score)',
            [$customerId, $points]
        );
    }

    public function getScore(int $customerId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_customer_score');

        $score = $connection->fetchOne(
            $connection->select()
                ->from($table, 'score')
                ->where('customer_id = ?', $customerId)
        );

        return $score !== false ? (int) $score : 0;
    }

    /**
     * All customers currently at or above a given score — the set-level counterpart to
     * getScore(), used by SegmentMemberResolver to resolve a "score_at_least" condition.
     *
     * @return int[]
     */
    public function getCustomerIdsWithScoreAtLeast(int $threshold): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_customer_score');
        $customerTable = $this->resourceConnection->getTableName('customer_entity');

        // ordo_customer_score has no FK to customer_entity (a deliberate, small-table
        // design — see this class's own docblock), so a deleted customer's row is never
        // cascade-cleaned and would otherwise still count as a match here. Confirmed via a
        // real CI run: two MFTF tests each created-then-deleted their own scored customer,
        // and a THIRD test's own segment still saw both as "matching" without this join.
        $ids = $connection->fetchCol(
            $connection->select()
                ->from(['s' => $table], 'customer_id')
                ->join(['c' => $customerTable], 's.customer_id = c.entity_id', [])
                ->where('s.score >= ?', $threshold)
        );

        return array_map('intval', $ids);
    }

    /**
     * Count-only counterpart to getCustomerIdsWithScoreAtLeast() - LoyaltyTierCalculator's
     * dashboard tier distribution only needs a number per threshold, not the actual customer
     * ids, so this skips fetching/hydrating a potentially large id list just to count it.
     */
    public function countCustomersWithScoreAtLeast(int $threshold): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_customer_score');
        $customerTable = $this->resourceConnection->getTableName('customer_entity');

        return (int) $connection->fetchOne(
            $connection->select()
                ->from(['s' => $table], ['count' => new \Zend_Db_Expr('COUNT(*)')])
                ->join(['c' => $customerTable], 's.customer_id = c.entity_id', [])
                ->where('s.score >= ?', $threshold)
        );
    }

    /**
     * Every registered customer, scored or not - a customer with no ordo_customer_score row
     * at all has an implicit score of 0 (see getScore()'s own false-to-0 fallback), which is
     * why LoyaltyTierCalculator's Bronze count is derived as "everyone else", not just
     * "customers with a low score row".
     */
    public function countAllCustomers(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');

        return (int) $connection->fetchOne(
            $connection->select()->from($customerTable, ['count' => new \Zend_Db_Expr('COUNT(*)')])
        );
    }

    /**
     * Current sum of matching ordo_score_rule points for a customer — kept in a separate
     * table (ordo_customer_demographic_score) from the running score total, so
     * EvaluateCustomerScoreRules can compute a delta between the old and new sum instead of
     * having to reverse out and reapply every rule's points on each customer save.
     */
    public function getDemographicScore(int $customerId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_customer_demographic_score');

        $score = $connection->fetchOne(
            $connection->select()
                ->from($table, 'score')
                ->where('customer_id = ?', $customerId)
        );

        return $score !== false ? (int) $score : 0;
    }

    public function setDemographicScore(int $customerId, int $score): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_customer_demographic_score');

        // Same upsert shape as addPoints(), but this one replaces the value outright (it's a
        // recomputed sum, not a delta), so VALUES(score) becomes the new score rather than an
        // increment.
        $connection->query(
            // phpcs:ignore Magento2.SQL.RawQuery.FoundRawSql
            'INSERT INTO ' . $connection->quoteIdentifier($table) . ' (customer_id, score) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE score = VALUES(score)',
            [$customerId, $score]
        );
    }
}
