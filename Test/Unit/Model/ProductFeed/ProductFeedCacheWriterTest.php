<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ProductFeed;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Ordo\Automation\Model\ProductFeed\ProductFeedCacheWriter;
use PHPUnit\Framework\TestCase;

class ProductFeedCacheWriterTest extends TestCase
{
    private AdapterInterface&\PHPUnit\Framework\MockObject\MockObject $connection;
    private ProductFeedCacheWriter $writer;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnCallback(fn (string $t) => $t);

        $this->writer = new ProductFeedCacheWriter($resourceConnection);
    }

    public function testWriteSuccessUpsertsFeedRow(): void
    {
        $this->connection->expects(self::once())->method('query')
            ->with(self::stringContains('ON DUPLICATE KEY UPDATE'), ['google_merchant', '<rss></rss>', 3]);

        $this->writer->writeSuccess('<rss></rss>', 3);
    }

    public function testWriteErrorUpsertsErrorRow(): void
    {
        $this->connection->expects(self::once())->method('query')
            ->with(self::stringContains('ON DUPLICATE KEY UPDATE'), ['google_merchant', 'boom']);

        $this->writer->writeError('boom');
    }

    public function testWriteErrorTruncatesLongMessages(): void
    {
        $longMessage = str_repeat('x', 300);

        $this->connection->expects(self::once())->method('query')
            ->with(self::anything(), self::callback(fn (array $params) => strlen($params[1]) === 255));

        $this->writer->writeError($longMessage);
    }
}
