<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ProductFeed;

use Magento\Framework\App\ResourceConnection;

/**
 * The one place ordo_product_feed_cache is written — shared by Cron\RefreshProductFeed (its own
 * schedule) and Controller\Adminhtml\ProductFeed\RefreshNow (on-demand), same reasoning as
 * Sms\MessageLogWriter being shared between SendSms and the delivery-status webhook.
 */
class ProductFeedCacheWriter
{
    private const string FEED_CODE = 'google_merchant';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function writeSuccess(string $xml, int $productCount): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_product_feed_cache');

        // ON DUPLICATE KEY UPDATE has no equivalent in Magento's query builder API; parameters
        // are still bound below, not interpolated (same pattern as RssFetcher::writeSuccess()).
        $connection->query(
            // phpcs:ignore Magento2.SQL.RawQuery.FoundRawSql
            'INSERT INTO ' . $connection->quoteIdentifier($table)
            . ' (feed_code, xml, product_count, generated_at, generation_error) VALUES (?, ?, ?, NOW(), NULL) '
            . 'ON DUPLICATE KEY UPDATE xml = VALUES(xml), product_count = VALUES(product_count), '
            . 'generated_at = VALUES(generated_at), generation_error = NULL',
            [self::FEED_CODE, $xml, $productCount]
        );
    }

    public function writeError(string $message): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_product_feed_cache');

        $connection->query(
            // phpcs:ignore Magento2.SQL.RawQuery.FoundRawSql
            'INSERT INTO ' . $connection->quoteIdentifier($table)
            . ' (feed_code, xml, product_count, generation_error) VALUES (?, \'\', 0, ?) '
            . 'ON DUPLICATE KEY UPDATE generation_error = VALUES(generation_error)',
            [self::FEED_CODE, substr($message, 0, 255)]
        );
    }
}
