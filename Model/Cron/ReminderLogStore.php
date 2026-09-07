<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Cron;

use Magento\Framework\App\ResourceConnection;

/**
 * "Count rows matching some conditions in a per-feature reminder/alert log table, or insert a
 * new one" — extracted after SonarCloud flagged the connection/select/fetchOne/insert boilerplate
 * duplicated across SendCreditLimitAlerts, SendOfferExpiryReminders, and SendReorderReminders.
 * Deliberately NOT a "has this already been sent" method with a unified signature: each caller's
 * actual condition differs (credit-limit checks a cooldown window, offer-expiry checks by type
 * with no date bound, reorder checks same-day only) — that's real business logic, not
 * boilerplate, so it stays in each cron and is passed through here as plain where-conditions.
 */
class ReminderLogStore
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @param array<string, int|string> $conditions Maps a Zend_Db_Select::where() condition
     *   string (e.g. 'customer_id = ?') to its bind value.
     */
    public function countMatching(string $table, array $conditions): int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()->from($this->resourceConnection->getTableName($table), 'COUNT(*)');

        foreach ($conditions as $condition => $value) {
            $select->where($condition, $value);
        }

        return (int) $connection->fetchOne($select);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->insert($this->resourceConnection->getTableName($table), $data);
    }

    /**
     * Rolls back a claim row written by insert() — every caller of insert() in this module now
     * writes the "already sent" row BEFORE calling the actual send (a claim, not an after-the-
     * fact log), so a crash between the insert and the send can never cause a duplicate send on
     * the next cron tick. If the send itself then genuinely fails (caught exception), the claim
     * must be undone here so the customer is retried on the next run instead of being
     * permanently skipped by a row that says "already sent" for a send that never happened.
     *
     * Deliberately deletes by matching the exact data insert() just wrote, not by a captured
     * entity_id/lastInsertId() - AdapterInterface (this store's only dependency, deliberately not
     * the concrete Zend adapter class) doesn't declare lastInsertId() at all, so relying on it
     * would make this store untestable without a real database connection.
     *
     * @param array<string, mixed> $data the exact same array just passed to insert()
     */
    public function deleteMatching(string $table, array $data): void
    {
        $connection = $this->resourceConnection->getConnection();

        $where = [];
        foreach ($data as $column => $value) {
            $where[] = $connection->quoteInto($connection->quoteIdentifier($column) . ' = ?', $value);
        }

        $connection->delete($this->resourceConnection->getTableName($table), implode(' AND ', $where));
    }
}
